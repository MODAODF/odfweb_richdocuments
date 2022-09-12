<?php

declare(strict_types=1);
/**
 * @author Lukas Reschke
 * @copyright 2014 Lukas Reschke lukas@owncloud.com
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OCA\Richdocuments\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\IRequest;
use OCP\IURLGenerator;
use \OCA\Richdocuments\AppConfig;
use OCP\AppFramework\Http;
use OCP\Files\IRootFolder;

class PDFController extends Controller
{

    /** @var IURLGenerator */
    private $urlGenerator;
    /** @var AppConfig */
    private $appConfig;
    /** @var IRootFolder */
    private $rootFolder;

    /**
     * @param string $AppName
     * @param IRequest $request
     * @param IURLGenerator $urlGenerator
     */
    public function __construct(
        string $AppName,
        AppConfig $appConfig,
        IRequest $request,
        IURLGenerator $urlGenerator,
        IRootFolder $rootFolder
    ) {
        parent::__construct($AppName, $request);
        $this->urlGenerator = $urlGenerator;
        $this->appConfig = $appConfig;
        $this->rootFolder = $rootFolder;
    }

    /**
     * @PublicPage
     * @NoCSRFRequired
     *
     * @param bool $minmode
     * @return TemplateResponse
     */
    public function checkConnect()
    {
        $apiUrl = $this->domainOnly($this->appConfig->getAppValue('public_wopi_url')) . "/lool/oxpdf/check";
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_URL, $apiUrl);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($curl, CURLOPT_TIMEOUT, 2);
        $res = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($httpCode != 200) {
            curl_close($curl);
            if($httpCode == 0){
                $res = "伺服器無法連接";
            }
            return new DataDisplayResponse($res, $httpCode);
        }
        return new DataDisplayResponse($res, $httpCode);
    }

    /**
     * Strips the path and query parameters from the URL.
     *
     * @param string $url
     * @return string
     */
    private function domainOnly($url)
    {
        $parsed_url = parse_url($url);
        $scheme = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
        $host   = isset($parsed_url['host']) ? $parsed_url['host'] : '';
        $port   = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
        $path   = isset($parsed_url['path']) ? $parsed_url['path'] : '';
        return "$scheme$host$port$path";
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param bool $minmode
     * @return TemplateResponse
     */
    public function toPDF($file)
    {
        $apiUrl = $this->domainOnly($this->appConfig->getAppValue('public_wopi_url')) . "/lool/oxpdf/merge";
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, '333');
        curl_setopt($curl, CURLOPT_URL, $apiUrl);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        $res = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($httpCode != 200) {
            print_r($res);
            curl_close($curl);
            if($httpCode == 0){
                $res = "伺服器無法連接";
            }
            return new DataDisplayResponse($res, $httpCode);
        }
        $data["access_token"] = str_replace("\"", "", $res);
        $fileHA=array();
        $filePath = explode("webdav", $file)[1];
        $fileN = \OC::$server->getUserFolder()->get($filePath);
        $tmph = tmpfile();
        fwrite($tmph, $fileN->getContent());
        $tmpf = stream_get_meta_data($tmph)['uri'];
        $data['pdf1'] =  curl_file_create($tmpf, $fileN->getMimetype(), $fileN->getName());
        array_push($fileHA, $tmph);
        curl_close($curl);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_URL, $apiUrl);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 90);
        curl_setopt($curl, CURLOPT_TIMEOUT, 90);

        $res = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($httpCode != 200) {
            curl_close($curl);
            if($httpCode == 0){
                $res = "伺服器無法連接";
            }
            return new DataDisplayResponse($res, $httpCode);
        }
        $temp_pointer = tmpfile();
        fwrite($temp_pointer, $res);
        $metaDatas = stream_get_meta_data($temp_pointer);
        $tmpFilename = $metaDatas['uri'];
        $response = new DataDisplayResponse($res);
        $response->addHeader('Content-type', "application/pdf");
        $response->addHeader('Content-Disposition', 'attachment; filename="result.pdf"');
        return $response;
    }
}
