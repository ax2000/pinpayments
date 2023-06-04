<?php

namespace pixelpie\craftpinpayments;

use yii\web\AssetBundle;
class PinPaymentsBundle extends AssetBundle
{
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $this->sourcePath = '@pixelpie/craftpinpayments/resources';

        $this->js = [
            'js/paymentForm.js',
        ];

        parent::init();
    }
}
