<?php
defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\ApiController;
use Joomla\CMS\Factory;

class NumistrControllerVariants extends ApiController
{
    protected $contentType = 'application/json';
    protected $default_view = 'variants';

    public function get()
    {
        $app   = Factory::getApplication();
        $model = $this->getModel('Variants');

        $querySig = $model->signatureFromInput($app->input);
        $etag = '"' . md5('variants|'.$querySig) . '"';

        if ($app->input->server->getString('HTTP_IF_NONE_MATCH') === $etag) {
            $app->setHeader('ETag', $etag, true);
            $app->setHeader('Cache-Control', 'public,max-age=60', true);
            $app->sendHeaders(); http_response_code(304); return;
        }

        [$items, $meta, $links] = $model->fetchList($app->input);
        $resp = $model->serializeList($items, $app->input->getString('fields',''), $app->input->getString('include',''));

        $app->setHeader('ETag', $etag, true);
        $app->setHeader('Cache-Control', 'public,max-age=60', true);
        $this->displayJson(['data'=>$resp, 'meta'=>$meta, 'links'=>$links]);
    }

    public function getItem()
    {
        $app   = Factory::getApplication();
        $uid   = $app->input->getString('id','');
        $model = $this->getModel('Variants');

        $row = $model->fetchOne($uid);
        if (!$row) {
            http_response_code(404);
            return $this->displayJson(['type'=>'about:blank','title'=>'Not Found','status'=>404,'detail'=>'variant not found']);
        }

        $data = $model->serializeOne($row, $app->input->getString('fields',''), $app->input->getString('include',''));
        $this->displayJson($data);
    }
}
