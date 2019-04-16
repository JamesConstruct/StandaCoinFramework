<?php

namespace JamesConstruct\StandaCoinFramework;


class Test {

}


class App {

    const NONCE_FILE = 'nonces.json';
    const SECURE = false;
    const BASEURI = 'http://localhost';
    protected $id, $key;


    /**
     * @param string $app_id
     * @param string $secret
     *
     * @return $this
     */
    public function __construct($app_id, $secret)
    {

        if (!ctype_xdigit($secret))
        {
            throw("StandaCoin: Neplatný app secret!");
        }

        $this->id = $app_id;
        $this->key = hex2bin($secret);

        if (!file_exists(self::NONCE_FILE))
        {
            file_put_contents(self::NONCE_FILE, null);
        }

    }


    private function __random_string($len=5)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randstring = '';
        for ($i = 0; $i < $len; $i++) {
            $randstring .= $characters[rand(0, strlen($characters)-1)];
        }
        return $randstring;
    }
    

    private function __gen_nonce($timestamp) : String
    {

        $nonces = file_get_contents(self::NONCE_FILE);
        if (!$nonces)
        {
            $nonces = [
                'time' => '0',
                'nonces' => [

                ]
            ];
        } else {
            $nonces = json_decode($nonces, true);
        }


        if ($nonces['time'] < $timestamp)
        {
            $nonces['time'] = $timestamp;
            $nonces['nonces'] = [];
        }

        $nonce = null;
        while ($nonce == null || in_array($nonce, $nonces['nonces']))
        {
            $nonce = $this->__random_string(10);
        }

        array_push($nonces['nonces'], $nonce);
        $json = json_encode($nonces);
        file_put_contents(self::NONCE_FILE, $json);

        return $nonce;

    }


    private function __sign($timestamp, $method, $uri, $data) : Array
    {

        $nonce = $this->__gen_nonce($timestamp);

        $sign_data = [$this->id, $timestamp, $nonce, $method, $uri];
        $all = array_merge($sign_data, $data);  // spojení s odesílanými daty

        $string = implode("\n", $all);

        $mac = hash_hmac('sha256', $string, $this->key);


        return [$mac, $nonce];

    }



    private function __get_time() : Int
    {

        return round(time() / 10) * 10;

    }



    private function __send($timestamp, $data, $endpoint, $method)
    {

        [$sign, $nonce] = $this->__sign($timestamp, $method, $endpoint, $data);

        $base = [
            'id' => $this->id,
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'mac' => $sign,
        ];

        $payload = array_merge($base, $data);

        
        $fields = http_build_query($payload);

        $ch = curl_init();

        curl_setopt( $ch, CURLOPT_URL, self::BASEURI . $endpoint . ($method == 'GET' ? '?'.$fields : '') );
        curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1 );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt( $ch, CURLOPT_POST, $method=='POST' );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, ($method == 'POST' ? $fields : null) );

        $content = curl_exec($ch);
        curl_close($ch);


        return json_decode($content, true);

    }

    /**
     * @param array $data
     * 
     * @return array
     */
    public function TestApi($data=['ping'=>'pong']) : array
    {

        $endpoint = '/api/v2/test';
        $method = 'POST';

        $timestamp = $this->__get_time();
        $data['require_sign'] = (int)self::SECURE;

        return $this->__send($timestamp, $data, $endpoint, $method);

    }


    /**
     * @param string $name
     * @param string $description
     * @param float $amount
     * @param string $return_url
     * @param string $state
     *
     * @return array
     */
    public function CreatePayment($name, $description, $amount, $return_url, $state=null) : array
    {

        $endpoint = '/api/v2/create';
        $method = 'POST';

        $timestamp = $this->__get_time();

        $data = [
            'name' => $name,
            'desc' => $description,
            'amount' => (float)$amount,
            'return_url' => $return_url,
            'state' => $state,
            'require_sign' => (int)self::SECURE
        ];


        $response = $this->__send($timestamp, $data, $endpoint, $method);


        return $response;

    }


    /**
     * @param int $id
     * 
     * @return array
     */
    public function GetPayment($id) : array
    {

        $endpoint = '/api/v2/payment';
        $method = 'POST';

        $timestamp = $this->__get_time();

        $data = [
            'payment_id' => $id,
            'require_sign' => (int)self::SECURE
        ];


        $response = $this->__send($timestamp, $data, $endpoint, $method);


        return $response;

    }


    /**
     * @param string $receiver
     * @param float $amount
     * 
     * @return array
     */
    public function SendPayment($receiver, $amount) : array
    {

        $endpoint = '/api/v2/send';
        $method = 'POST';

        $timestamp = $this->__get_time();

        $data = [
            'receiver' => $receiver,
            'amount' => (float)$amount,
            'require_sign' => (int)self::SECURE
        ];


        $response = $this->__send($timestamp, $data, $endpoint, $method);


        return $response;

    }


}
