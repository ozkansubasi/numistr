<?php
defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\ApiController;
use Joomla\CMS\Factory;

class NumistrControllerFeed extends ApiController
{
    protected $contentType = 'application/json';

    public function get()
    {
        $app   = Factory::getApplication();
        $model = $this->getModel('Feed');

        $etag = '"' . md5('feed|v1') . '"';
        if ($app->input->server->getString('HTTP_IF_NONE_MATCH') === $etag) {
            $app->setHeader('ETag', $etag, true);
            $app->setHeader('Cache-Control', 'public,max-age=300', true);
            $app->sendHeaders(); http_response_code(304); return;
        }

        $this->displayJson($model->fetch());
    }
}
