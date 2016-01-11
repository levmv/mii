<?php

namespace mii\captcha;


class Controller extends \mii\web\Controller {

    public $render_layout = false;

    protected function access_rules()
    {
        $this->acl->allow('*');
    }

    public function index() {
        \Mii::$app->captcha->render(FALSE);
    }


    public function after($content = null)
    {
        \Mii::$app->captcha->update_response_session();

        return $this->response;
    }


}