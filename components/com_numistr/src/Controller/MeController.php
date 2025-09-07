<?php
defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\ApiController;

class NumistrControllerMe extends ApiController
{
    protected $contentType = 'application/json';

    public function get()
    {
        // TODO: Read user from token. For now, anonymous stub:
        $this->displayJson(['user_id'=>null,'entitlements'=>[]]);
    }
}
