<?php

namespace mii\rest;

use mii\core\Component;

class Client extends Component {

    public $base_uri = '';
    public $user_agent = '';

    public $username;
    public $password;

    public $params = [];
    public $headers = [];

    public $curl_options;


    public function get($url, $params = null, $headers = []) : Response {
        return $this->execute('GET', $url, $params, $headers);
    }

    public function post($url, $params = null, $headers = []) : Response {
        return $this->execute('POST', $url, $params, $headers);
    }

    public function put($url, $params = null, $headers = []) : Response {
        return $this->execute('PUT', $url, $params, $headers);
    }

    public function delete($url, $params = null, $headers = []) : Response {
        return $this->execute('DELETE', $url, $params, $headers);
    }

    public function execute($method, $url, $params = null, $headers = []) : Response {

        $curl_handle = curl_init();

        $curlopt = [
            CURLOPT_HEADER => TRUE,
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_USERAGENT => $this->user_agent
        ];

        if($this->username && $this->password)
            $curlopt[CURLOPT_USERPWD] = "{$this->username}:{$this->password}";

        if(count($this->headers) || count($headers)){
            $curlopt[CURLOPT_HTTPHEADER] = [];
            $headers = array_merge($this->headers, $headers);
            foreach($headers as $key => $values){
                foreach(is_array($values)? $values : [$values] as $value){
                    $curlopt[CURLOPT_HTTPHEADER][] = "$key:$value";
                }
            }
        }

        // Allow passing parameters as a pre-encoded string (or something that
        // allows casting to a string). Parameters passed as strings will not be
        // merged with parameters specified in the default options.
        if(is_array($params)){
            $params = array_merge($this->params, $params);
            $parameters_string = http_build_query($params);

        }
        else
            $parameters_string = (string) $params;

        if(strtoupper($method) == 'POST'){
            $curlopt[CURLOPT_POST] = TRUE;
            $curlopt[CURLOPT_POSTFIELDS] = $parameters_string;
        }
        elseif(strtoupper($method) != 'GET'){
            $curlopt[CURLOPT_CUSTOMREQUEST] = strtoupper($method);
            $curlopt[CURLOPT_POSTFIELDS] = $parameters_string;
        }
        elseif($parameters_string){
            $url .= strpos($url, '?') ? '&' : '?';
            $url .= $parameters_string;
        }

        $url = rtrim($this->base_uri, '/') . '/' . ltrim($url, '/');

        $curlopt[CURLOPT_URL] = $url;

        if($this->curl_options){
            // array_merge would reset our numeric keys.
            foreach($this->curl_options as $key => $value){
                $curlopt[$key] = $value;
            }
        }
        curl_setopt_array($curl_handle, $curlopt);


        $response = curl_exec($curl_handle);
        $info = (object) curl_getinfo($curl_handle);
        $error = curl_error($curl_handle);

        curl_close($curl_handle);

        return new Response($response, $url, $info, $error);
    }


    private function format_url($url) {
        $url = $this->base_uri . '/' . ltrim($url, '/');
    }


}