<?php

namespace mii\captcha;


class Controller extends \mii\web\Controller {

    public $render_layout = false;

    protected function access_rules()
    {
        $this->acl->allow('*');
    }

    public function index() {
        \Mii::$app->captcha->update_response_session();
        \Mii::$app->captcha->render(FALSE);
    }


}