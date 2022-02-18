<?php

/*
 * (c) Nakagawa.dev <Jonnathan Nakagawa> (jonnakagawadev@gmail.com)
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace nakagawadev;

/**
 * GitLabOauth2 class
 */
class GitLabOauth2
{
    /**
     * Dominio de la API
     */
    private $DOMAIN = 'https://gitlab.com';

    /**
     *
     */
    private $API_PATH = 'api/v4';

    /**
     * App ID
     */
    private $APP_ID;

    /**
     * App secret
     */
    private $APP_SECRET;

    /**
     * Redirect URI
     */
    private $REDIRECT_URI;

    /**
     * State
     */
    private $STATE;

    /**
     * Scopes
     */
    private $SCOPES;

    /**
     * Client
     */
    private $CLIENT;

    /**
     * setConfig()
     * Sets the configuration for the client
     *
     * @param array $data
     * @return void
     */
    public function setConfig(array $data): void
    {
        $this->APP_ID = $data['app_id'];
        $this->APP_SECRET = $data['app_secret'];
        $this->REDIRECT_URI = $data['redirect_uri'];
        $this->STATE = $data['state'] ?? '';
        $this->SCOPES = isset($data['scopes']) ? $this->parseScope($data['scopes']) : 'api';
        if(isset($data['domain']))
            $this->DOMAIN = $data['domain'];
    }

    /**
     * getAuthUrl()
     * Generates an authorization URL
     *
     * @return string
     */
    public function getAuthUrl(): string
    {
        return "{$this->DOMAIN}/oauth/authorize?client_id={$this->APP_ID}&redirect_uri={$this->REDIRECT_URI}&response_type=code&state={$this->STATE}&scope={$this->SCOPES}";
    }

    /**
     * getAccessToken()
     * Gets the access token
     *
     * @param string $code
     * @return mixed
     */
    public function getAccessToken(string $code)
    {
        $data = $this->cURL([
            'url' => "{$this->DOMAIN}/oauth/token",
            'post' => [
                'client_id' => $this->APP_ID,
                'client_secret' => $this->APP_SECRET,
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $this->REDIRECT_URI,
            ],
        ]);
        // if($data->status != 200)
        //     return false;
        $this->CLIENT = json_decode($data->response);
        return $this->CLIENT;
    }

    /**
     * setAccessToken()
     * Sets the access token
     *
     * @param array $data_client
     * @return void
     */
    public function setAccessToken($data_client): void
    {
        $this->CLIENT = $data_client;
    }

    /**
     * isAccessTokenExpired()
     * Determines if the access token has expired
     *
     * @return boolean Returns True if the access token is expired
     */
    public function isAccessTokenExpired(): bool
    {
        if(!isset($this->CLIENT->access_token))
            return true;
        $created = 0;
        $data = $this->cURL([
            'url' => "{$this->DOMAIN}/oauth/token/info",
            'headers' => ["Authorization: Bearer {$this->CLIENT->access_token}"]
        ]);
        $response = json_decode($data->response);
        if(isset($response->error))
            return true;
        $created = $response->created_at;
        // If the token expires within the next 20 seconds
        return ($created + ($response->expires_in - 20) < time());
    }

    /**
     * refreshAccessToken()
     * Renew access token
     *
     * @return mixed
     */
    public function refreshAccessToken()
    {
        $data = $this->cURL([
            'url' => "{$this->DOMAIN}/oauth/token",
            'post' => [
                'client_id' => $this->APP_ID,
                'client_secret' => $this->APP_SECRET,
                'refresh_token' => $this->CLIENT->refresh_token,
                'grant_type' => 'refresh_token',
                'redirect_uri' => $this->REDIRECT_URI,
            ]
        ]);
        $response = json_decode($data->response);
        if(isset($response->error)) 
            return false;
        $this->setAccessToken($response);
        return $response;
    }

    /**
     * createSecureState()
     * Generates a cryptographically secure pseudorandom string
     *
     * @return string
     */
    public function createSecureState(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * verifySecureState()
     * Verify if the hash "state" is valid
     *
     * @param string $real_state
     * @param string $user_state
     * @return boolean
     */
    public function verifySecureState(string $real_state, string $user_state): bool
    {
        return hash_equals($real_state, $user_state);
    }

    /**
     * api()
     * Simple method to interact with gitlab api
     *
     * @param string $endpoint
     * @param array $data_post
     * @return object
     */
    public function api(string $endpoint, array $data_post = [])
    {
        $request_data = [
            'url' => "{$this->DOMAIN}/{$this->API_PATH}/{$endpoint}",
            'headers' => ["Authorization: Bearer {$this->CLIENT->access_token}"]
        ];
        if(!empty($data_post))
            $request_data['post'] = $data_post;
        $data = $this->cURL($request_data);
        return $data;
    }

    /**
     * cURL()
     *
     * @param array $data
     * @return object
     */
    private function cURL(array $data): object
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $data['url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if(isset($data['headers']))
            curl_setopt($ch, CURLOPT_HTTPHEADER, $data['headers']);
        if(isset($data['post']))
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data['post']);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return (object) ['status' => $status, 'response' => $response];
    }

    /**
     * parseScope()
     *
     * @param mixed $scopes
     * @return string
     */
    private function parseScope($scopes): string
    {
        if(is_array($scopes))
            $scopes = implode('+', $scopes);
        return $scopes;
    }
}
