<?php
/**
 * Testing PrestaShop order update
 * @author Qapla' www.qapla.it
 */
class Prestashop
{
    const VERSION = '1.2.17';

    private $url, $key;

    protected $ch;

    /**
     * @param $url
     * @param $key
     * @throws Exception
     */
    public function __construct($url, $key)
    {
        $this->url = $url . '/api/';
        $this->key = $key;

        $this->ch = curl_init(); //orders?limit=1

        $defaultParams = [
            CURLOPT_HEADER => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLINFO_HEADER_OUT => true,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $this->key . ':',
            CURLOPT_HTTPHEADER => ['Expect:'],
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.0.3) Gecko/20060426 Firefox/1.5.0.3',
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0
        ];

        curl_setopt_array($this->ch, $defaultParams);

        //... Check connection
        $this->exec(['resource' => 'orders', 'limit' => 1]);
    }

    /**
     * @param $reference
     * @param $status
     * @param null $trackingNumber
     * @throws Exception
     */
    function orderUpdate($reference, $status, $trackingNumber = null)
    {
        //... get the order by reference
        $order = $this->exec(['resource' => 'orders', 'filter[reference]' => $reference], [CURLOPT_CUSTOMREQUEST => 'GET']);

        //... obtain order ID
        $id = $this->getOrderID($order);

        //... for testing purpose
        echo 'ID: '.$id.'<hr/>';

        //... get the order template
        $xml = $this->exec(['resource' => 'orders', 'id' => $id], [CURLOPT_CUSTOMREQUEST => 'GET']);

        $xml->order->current_state = $status;

        //... Check if tracking number is already updated on PrestaShop
        $orderTrackingNumber = trim($xml->order->shipping_number->__toString());

        if (!empty($trackingNumber) && empty($orderTrackingNumber)):
            $xml->order->shipping_number = $trackingNumber;
        endif;

        //... Update order
        $this->exec(['resource' => 'orders', 'id' => $id], [CURLOPT_CUSTOMREQUEST => 'PUT', CURLOPT_POSTFIELDS => $xml->asXML()]);
    }

    /**
     * @param $order
     * @return int
     * @throws Exception
     */
    function getOrderID($order)
    {
        if (empty($order->children()->children())):
            throw new Exception('Order not found');
        endif;

        return (int)$order->children()->children()->attributes();
    }

    /**
     * @param $options
     * @param array $parameters
     * @return false|SimpleXMLElement
     * @throws Exception
     */
    function exec($options, $parameters = [])
    {
        if (!isset($options['resource'])):
            throw new Exception('Resource not set');
        endif;

        if (!empty($parameters)):
            curl_setopt_array($this->ch, $parameters);
        endif;

        $url = $this->url . $options['resource'];

        $url_params = [];

        if (isset($options['id'])):
            $url .= '/' . $options['id'];
        endif;

        $params = ['filter', 'display', 'sort', 'limit', 'id_shop', 'id_group_shop', 'date'];
        foreach ($params as $p):
            foreach ($options as $k => $o):
                if (strpos($k, $p) !== false):
                    $url_params[$k] = $o;
                endif;
            endforeach;
        endforeach;

        if (count($url_params) > 0):
            $url .= '?' . http_build_query($url_params);
        endif;

        curl_setopt($this->ch, CURLOPT_URL, $url);

        $result = curl_exec($this->ch);

        $index = strpos($result, "\r\n\r\n");

        if ($index === false):
            throw new Exception('Bad HTTP response');
        endif;

        //$header = substr($result, 0, $index);
        $body = substr($result, $index + 4);

        $statusCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);

        if ($statusCode === 0):
            throw new Exception('CURL Error: ' . curl_error($this->ch));
        endif;

        if (!in_array($statusCode, [200, 201])):
            throw new Exception((array_key_exists('CURLOPT_CUSTOMREQUEST', $parameters) ? $parameters[CURLOPT_CUSTOMREQUEST] : 'GET'). ' : endpoint:' . $options['resource'] . (isset($options['id']) ? ':' . $options['id'] : '') . ' error: ' . $statusCode);
        endif;

        return $this->parseXML($body);
    }

    /**
     * @param $response
     * @return false|SimpleXMLElement
     * @throws Exception
     */
    private function parseXML($response)
    {
        if ($response != ''):
            libxml_clear_errors();
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($response,'SimpleXMLElement', LIBXML_NOCDATA);
            if (libxml_get_errors()):
                $msg = var_export(libxml_get_errors(), true);
                libxml_clear_errors();
                throw new Exception('HTTP XML response is not parsable: '.$msg);
            endif;
            return $xml;
        else:
            throw new Exception('HTTP response is empty');
        endif;
    }
}

try {
    $url = 'https://foo.foo';
    $apiKey = 'API_KEY';
    $reference = 'ORDER_REFERENCE';
    $trackinNumber = 'FOO';
    $newStatusID = 4; //shipped
    //$newStatusID = 5; //delivered

    echo 'CONNECTING TO '.$url.'<hr/>';

    $prestashop = new Prestashop($url, $apiKey);

    echo 'CONNECTED<hr/>';

    echo $reference.' &bull; '.$trackinNumber.'<hr/>';

    $prestashop->orderUpdate($reference, $newStatusID, $trackinNumber);

    echo 'UPD';

} catch (Exception $e) {
    echo 'ERROR: ' .  $e->getMessage();
}