<?php
defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\ApiController;

class NumistrControllerRegions extends ApiController
{
    protected $contentType = 'application/json';
    protected $default_view = 'regions';

    public function get()
    {
        $items = $this->getModel('Regions')->fetchList();
        $this->displayJson(['data'=>$items,'meta'=>['total'=>count($items)]]);
    }

    public function getItem()
    {
        $code = $this->input->getString('id','');
        $row  = $this->getModel('Regions')->fetchOne($code);
        if (!$row) { http_response_code(404); return $this->displayJson(['type'=>'about:blank','title'=>'Not Found','status'=>404]); }
        $this->displayJson($row);
    }
}
