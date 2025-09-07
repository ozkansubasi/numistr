<?php
defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\ApiController;

class NumistrControllerBillingreceipt extends ApiController
{
    protected $contentType = 'application/json';

    public function post()
    {
        // TODO: Validate receipt with Apple/Google. For now stub:
        $this->displayJson(['entitlements'=>[['name'=>'pro','active'=>false]]]);
    }
}
