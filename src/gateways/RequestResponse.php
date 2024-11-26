<?php
namespace pixelpie\craftpinpayments\gateways;

use craft\commerce\omnipay\base\RequestResponse as BaseRequestResponse;
class RequestResponse extends BaseRequestResponse
{
    public function isSuccessful(): bool
    {
        $responseData = $this->response->getData();
        return $responseData['response']['status_message'] !== 'Pending' && $this->response->isSuccessful();
    }

    /**
     * @inheritdoc
     */
    public function isProcessing(): bool
    {
        $responseData = $this->response->getData();
        return $responseData['response']['status_message'] === 'Pending';
    }

    /**
     * @inheritdoc
     */
    public function isRedirect(): bool
    {
        $responseData = $this->response->getData();
        return $responseData['response']['status_message'] === 'Pending';
    }
}

