<?php
defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\ApiController;
use Joomla\CMS\Factory;

class NumistrControllerMints extends ApiController
{
    protected $contentType = 'application/json';
    protected $default_view = 'mints';

    public function get()
    {
        $app   = Factory::getApplication();
        $model = $this->getModel('Mints');

        $etag = '"' . md5('mints|'.$model->signatureFromInput($app->input)) . '"';
        if ($app->input->server->getString('HTTP_IF_NONE_MATCH') === $etag) {
            $app->setHeader('ETag', $etag, true);
            $app->setHeader('Cache-Control', 'public,max-age=300', true);
            $app->sendHeaders(); http_response_code(304); return;
        }

        [$items,$meta,$links] = $model->fetchList($app->input);
        $this->displayJson(['data'=>$items,'meta'=>$meta,'links'=>$links]);
    }

    public function getItem()
    {
        $app   = Factory::getApplication();
        $slug  = $app->input->getString('id','');
        $model = $this->getModel('Mints');

        $row = $model->fetchOne($slug);
        if (!$row) { http_response_code(404); return $this->displayJson(['type'=>'about:blank','title'=>'Not Found','status'=>404]); }
        $this->displayJson($row);
    }
}
