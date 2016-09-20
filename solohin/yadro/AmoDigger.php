<?php
/**
 * Created by PhpStorm.
 * User: solohin
 * Date: 14.09.16
 * Time: 8:07
 */
namespace solohin\yadro;

use Psr\Log\AbstractLogger;

class AmoDigger
{
    /** @var AbstractLogger */
    private $logger;
    private $wailLockFile = null;
    private $credentials = [];

    const ELEMENT_TYPE_CONTACT = 1;
    const ELEMENT_TYPE_LEAD = 2;
    const ELEMENT_TYPE_COMPANY = 3;
    const ELEMENT_TYPE_TASK = 4;

    public function __construct($domain, $login, $hash, AbstractLogger $logger)
    {
        $this->logger = $logger;
        $this->credentials = [
            'domain' => $domain,
            'login' => $login,
            'hash' => $hash,
        ];
        $this->wailLockFile = dirname(dirname(__DIR__)) . '/lock.txt';
        $this->logger->info('AmoDigger created');
    }

    public function get($what, $fromTS = 0)
    {
        if ($what === 'account') {
            return $this->getAccount();
        }
        if ($what === 'companies') {
            return $this->getCompanies($fromTS);
        }
        if (in_array($what, ['notes_contact', 'notes_lead', 'notes_task', 'notes_company'])) {
            $type = str_replace('notes_', '', $what);
            return $this->getNotes($fromTS, $type);
        }

        return $this->fetch($what . '/list', $what, $fromTS);
    }

    private function getNotes($fromTS, $type)
    {
        return $this->fetch('notes/list?type=' . $type, 'notes', $fromTS);
    }

    private function getCompanies($fromTS)
    {
        return $this->fetch('company/list', 'contacts', $fromTS);
    }

    private function getAccount()
    {
        $response = $this->request('accounts/current');
        return [$response['response']['account']];
    }

    public function fetch($url, $resultKey, $fromTS = 0)
    {
        $page = 0;
        $limit = 500;
        $result = [];
        while (true) {
            $params = ['limit_rows' => $limit, 'limit_offset' => $limit * $page, 'deleted' => 'Y'];

            $this->logger->info('Requesting ' . $url . ', page ' . $page);

            $temp = $this->request(
                $url,
                $params,
                $fromTS
            );
            if (isset($temp['response'][$resultKey][0])) {
                $result = array_merge($temp['response'][$resultKey], $result);
                //Перестрахуемся
                if (count($temp['response'][$resultKey]) < (int)($limit/2)) {
                    break;
                }
            } else {
                break;
            }
            $page++;

            if ($page > 500) {
                $this->logger->warning('Over 500 pages of ' . $resultKey . ' exceeded. Cut results tp 500 pages');
            }
        }
        return $result;
    }

    private function error($message, $data)
    {
        $this->logger->error($message, $data);
        throw new \Exception($message);
    }

    private function request($path, $params = [], $fromTS = 0)
    {
        $params['USER_LOGIN'] = $this->credentials['login'];
        $params['USER_HASH'] = $this->credentials['hash'];

        $url = 'https://' . $this->credentials['domain'] . '.amocrm.ru/private/api/v2/json/';
        $url .= $path;
        $url .= (strpos($path, '?') === false) ? '?' : '&';
        $url .= http_build_query($params);

        $curl = multiNetwork::waitAndGetNextCurlIface($this->wailLockFile);

        if ($fromTS > 0) {
            curl_setopt(
                $curl,
                CURLOPT_HTTPHEADER,
                ['IF-MODIFIED-SINCE: ' . date('D, d M Y H:i:s', $fromTS)]
            );
        }

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_USERAGENT, 'amoCRM-API-client/1.0');
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);

        $out = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $code = (int)$code;
        $errors = array(
            301 => 'Moved permanently',
            400 => 'Bad request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not found',
            500 => 'Internal server error',
            502 => 'Bad gateway',
            503 => 'Service unavailable'
        );
        if ($code != 200 && $code != 204) {
            $this->error(
                ((isset($errors[$code])) ? $errors[$code] : 'Undescribed error ') . $code,
                [
                    'credentials' => $this->credentials,
                    'link' => $url,
                    'code' => $code,
                    'out' => json_decode($out, 1),
                ]
            );
        }
        return json_decode($out, 1);
    }
}