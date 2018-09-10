<?php
/**
 * @copyright Copyright (c) 2016, AMIV an der ETH
 *
 * @author Sandro Lutz <code@temparus.ch>
 * @author Marco Eppenberger <mail@mebg.ch>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

class ApiUtil {

    /**
     * Send GET request to AMIV API
     * 
     * @param string $request
     * @param string $token
     */
    public static function get($request, $token=null) {
        return self::rawreq($request, null, null, $token);
    }

    /**
     * Send POST request to AMIV API
     * 
     * @param string $request
     * @param string $postData
     * @param string $token
     */
    public static function post($request, $postData, $token=null) {
        return self::rawreq($request, $postData, null, $token);
    }

    /**
     * send DELETE request to AMIV API
     * 
     * @param string $request
     * @param string $etag
     * @param string $token
     */
    public static function delete($request, $etag, $token=null) {
        return self::rawreq($request, null, $etag, $token, 'DELETE');
    }

    /**
     * Assemble request and send it
     * 
     * @param string $request
     * @param string $postData
     * @param string $etag
     * @param string $token
     */
    private static function rawreq($request, $postData=null, $etag=null, $argToken=null, $customMethod=null) {
        global $wgAmivAuthenticationApiUrl, $wgAmivAuthenticationApiKey;

        if (!$wgAmivAuthenticationApiUrl) {
            return [404, "API server not defined"];
        }

        if ($argToken) {
            $token = $argToken;
        } else {
            $token = $wgAmivAuthenticationApiKey;
        }

        $ch = curl_init();

        if ($customMethod != null) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $customMethod);
        }

        curl_setopt($ch, CURLOPT_URL, $wgAmivAuthenticationApiUrl.'/'.$request);
        
        if ($postData != null) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); // timeout in seconds
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        $header = [];
        if ($token != null) {
            $header[] = 'Authorization: ' .$token;
        }
        if ($etag != null) {
            $header[] = 'If-Match: ' .$etag;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        $response = json_decode(curl_exec($ch));
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close ($ch);
        return [$httpcode, $response];
    }
}
