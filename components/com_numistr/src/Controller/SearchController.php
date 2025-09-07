<?php
defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\ApiController;

class NumistrControllerSearch extends ApiController
{
    protected $contentType = 'application/json';

    public function get()
    {
        $q = trim($this->input->getString('q',''));
        // TODO: Delegate to OpenSearch proxy; now returns a dummy list.
        $this->displayJson([
            'hits' => $q ? [[
                'type'=>'variant','uid'=>'ntr:var:DEMO1','score'=>0.91,
                'snippet'=>'...legend match...', 'thumb_url'=>'https://example.org/i/demo_t.webp'
            ]] : []
        ]);
    }
}
