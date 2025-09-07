<?php
defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\ApiController;

class NumistrControllerVisionmatch extends ApiController
{
    protected $contentType = 'application/json';

    public function post()
    {
        // TODO: enqueue job, return job_id
        $this->displayJson(['job_id'=>'vm_demo_1']);
    }

    public function get()
    {
        $jobId = $this->input->getString('id','');
        // TODO: status lookup
        $this->displayJson(['status'=>'processing','candidates'=>[],'job_id'=>$jobId]);
    }
}
