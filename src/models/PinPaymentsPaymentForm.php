<?php

namespace pixelpie\craftpinpayments\models;

use craft\commerce\models\payments\CreditCardPaymentForm;
use craft\commerce\models\PaymentSource;

/**
 * Pin Payments form model.
 *
 * @author    Pixel Pie. <support@pixelpie.com.au>
 * @since     1.0
 */
class PinPaymentsPaymentForm extends CreditCardPaymentForm
{
    /**
     * @var string|null
     */
    public ?string $encryptedCardNumber = null;

    /**
     * @var string|null
     */
    public ?string $encryptedCardCvv = null;

    /**
     * @var string|null credit card reference
     */
    public ?string $cardReference = null;

    /**
     * @var string|null Full name on the card
     */
    public ?string $fullName = null;

    /**
     * @inheritdoc
     */
    public function populateFromPaymentSource(PaymentSource $paymentSource): void
    {
        $this->cardReference = $paymentSource->token;
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        if (empty($this->cardReference)) {
            $currentYear = intval(date('y'));
            return [
                [['fullName', 'month', 'year', 'number', 'cvv'], 'required'],
                [['month'], 'integer', 'integerOnly' => true, 'min' => 1, 'max' => 12],
                [['year'], 'integer', 'integerOnly' => true, 'min' => $currentYear, 'max' => $currentYear + 12],
            ];
        }

        return [];
    }
}