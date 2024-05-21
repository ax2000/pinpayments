<?php

namespace pixelpie\craftpinpayments\gateways;

use craft\commerce\models\Transaction;
use pixelpie\craftpinpayments\models\PinPaymentsPaymentForm;

use Craft;
use craft\commerce\errors\PaymentException;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\omnipay\base\CreditCardGateway;
use craft\helpers\App;
use craft\helpers\Html;
use craft\web\View;
use Omnipay\Common\AbstractGateway;
use Omnipay\Common\Message\ResponseInterface;
use Omnipay\Pin\Gateway as OmnipayGateway;
use Omnipay\Pin\Message\Response;
use Throwable;
use yii\base\NotSupportedException;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

/**
 * Gateway represents Pin Payments gateway
 *
 * @author    Pixel Pie <support@pixelpie.com.au>
 * @since     1.0
 *
 * @property bool|string $testMode
 * @property null|string $apiKey
 * @property null|string $publishableKey
 * @property-read null|string $settingsHtml
 */
class Gateway extends CreditCardGateway
{
    /**
     * @var string|null
     */
    private ?string $_apiKey = null;

    /**
     * @var string|null
     */
    private ?string $_publishableKey = null;

    /**
     * @var bool|string
     */
    private bool|string $_testMode = false;


    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('commerce', 'Pin Payments');
    }

    /**
     * @inheritdoc
     */
    public function getSettings(): array
    {
        $settings = parent::getSettings();
        $settings['apiKey'] = $this->getApiKey(false);
        $settings['publishableKey'] = $this->getPublishableKey(false);
        $settings['testMode'] = $this->getTestMode(false);

        return $settings;
    }

    /**
     * @param bool $parse
     * @return bool|string
     * @since 4.0.0
     */
    public function getTestMode(bool $parse = true): bool|string
    {
        return $parse ? App::parseBooleanEnv($this->_testMode) : $this->_testMode;
    }

    /**
     * @param bool|string $testMode
     * @return void
     * @since 4.0.0
     */
    public function setTestMode(bool|string $testMode): void
    {
        $this->_testMode = $testMode;
    }

    /**
     * @param bool $parse
     * @return string|null
     * @since 4.0.0
     */
    public function getApiKey(bool $parse = true): ?string
    {
        return $parse ? App::parseEnv($this->_apiKey) : $this->_apiKey;
    }

    /**
     * @param string|null $apiKey
     * @return void
     * @since 4.0.0
     */
    public function setApiKey(?string $apiKey): void
    {
        $this->_apiKey = $apiKey;
    }

    /**
     * @param bool $parse
     * @return string|null
     * @since 4.0.0
     */
    public function getPublishableKey(bool $parse = true): ?string
    {
        return $parse ? App::parseEnv($this->_publishableKey) : $this->_publishableKey;
    }

    /**
     * @param string|null $password
     * @return void
     * @since 4.0.0
     */
    public function setPublishableKey(?string $publishableKey): void
    {
        $this->_publishableKey = $publishableKey;
    }

    /**
     * @inheritdoc
     */
    public function getPaymentConfirmationFormHtml(array $params): string
    {
        return $this->_displayFormHtml($params, 'commerce-eway/confirmationForm');
    }


    /**
     * @inheritdoc
     */
    public function getPaymentFormHtml(array $params): ?string
    {
        return $this->_displayFormHtml($params, 'pin-payments/paymentForm');
    }

    /**
     * @inheritdoc
     */
    public function getPaymentFormModel(): PinPaymentsPaymentForm
    {
        return new PinPaymentsPaymentForm();
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('pin-payments/gatewaySettings', ['gateway' => $this]);
    }

    /**
     * @inheritdoc
     */
    public function populateRequest(array &$request, BasePaymentForm $paymentForm = null): void
    {
        /** @var PinPaymentsPaymentForm |null $paymentForm */
        if ($paymentForm) {
            $request['encryptedCardNumber'] = $paymentForm->encryptedCardNumber ?? null;
            $request['encryptedCardCvv'] = $paymentForm->encryptedCardCvv ?? null;

            $request['cardReference'] = $paymentForm->cardReference ?? null;
        }
    }

    /**
     * @inheritdoc
     */
    public function purchase(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        try{
            if (!$this->supportsPurchase()) {
                throw new NotSupportedException(Craft::t('commerce', 'Purchasing is not supported by this gateway'));
            }

            $request = $this->createRequest($transaction, $form);
            $purchaseRequest = $this->preparePurchaseRequest($request);

            return $this->performRequest($purchaseRequest, $transaction);
        } catch (\Exception $e) {
            Craft::$app->getSession()->setFlash('error', 'An error occurred: ' . $e->getMessage());
            throw new PaymentException('An error occurred: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @inheritdoc
     */
    protected function createGateway(): AbstractGateway
    {
        /** @var OmnipayGateway $gateway */
        $gateway = static::createOmnipayGateway($this->getGatewayClassName());

        $gateway->setSecretKey($this->getApiKey());
        $gateway->setTestMode($this->getTestMode());

        return $gateway;
    }

    /**
     * @inheritdoc
     */
    protected function extractCardReference(ResponseInterface $response): string
    {
        /** @var Response $response */
        if ($response->getCode() !== 'A2000') {
            throw new PaymentException($response->getMessage());
        }

        return $response->getCardReference();
    }


    /**
     * @inheritdoc
     */
    protected function extractPaymentSourceDescription(ResponseInterface $response): string
    {
        $data = $response->getData();

        return Craft::t('commerce', 'Payment card {masked}', ['masked' => $data['Customer']['CardDetails']['Number']]);
    }

    /**
     * @inheritdoc
     */
    protected function getGatewayClassName(): ?string
    {
        return '\\' . OmnipayGateway::class;
    }

    /**
     * Display a payment form from HTML based on params and template path
     *
     * @param array $params   Parameters to use
     * @param string $template Template to use
     *
     * @return string
     * @throws Throwable if unable to render the template
     */
    private function _displayFormHtml(array $params, string $template): string
    {
        $defaults = [
            'gateway' => $this,
            'paymentForm' => $this->getPaymentFormModel(),
            'handle' => $this->handle,
        ];

        $params = array_merge($defaults, $params);

        $view = Craft::$app->getView();

        $previousMode = $view->getTemplateMode();
        $view->setTemplateMode(View::TEMPLATE_MODE_CP);

        $html = Craft::$app->getView()->renderTemplate($template, $params);

        $view->setTemplateMode($previousMode);

        return $html;
    }
}