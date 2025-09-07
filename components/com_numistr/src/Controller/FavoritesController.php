<?php
defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\ApiController;

class NumistrControllerFavorites extends ApiController
{
    protected $contentType = 'application/json';

    public function get()    { $this->displayJson(['data'=>[]]); } // TODO: Auth + list
    public function post()   { http_response_code(501); $this->displayJson(['title'=>'Not Implemented','status'=>501]); }
    public function delete() { http_response_code(501); $this->displayJson(['title'=>'Not Implemented','status'=>501]); }
}
