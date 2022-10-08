<?php

namespace Icinga\Module\Guacamole\ProvidedHook\Director;

use Icinga\Exception\InvalidPropertyException;
use Icinga\Exception\AuthenticationException;
use Icinga\Module\Director\Hook\ImportSourceHook;
use Icinga\Module\Director\RestApi\RestApiClient;
use Icinga\Module\Director\Web\Form\QuickForm;
use InvalidArgumentException;

class ImportSource extends ImportSourceHook
{
    public function getName()
    {
        return 'Guacamole REST API';
    }

    public function fetchData()
    {
        try {
            $auth_headers['Guacamole-Token'] = $this->getGuacamoleAuthToken();
        }
        catch (Exception $e) {
            throw new AuthenticationException('Problem connecting to Guacamole API: '.$e->getMessage());
        }


        // Retrieve connectionGroups
        $connectionsGroups = $this->getRestApi()->get(
            "/api/session/data/{$this->getSetting('backendtype')}/connectionGroups/ROOT/tree",
            null,
            $auth_headers
        );

        // Retrieve childConnection details
        //$columns = $this->listColumns($connectionsGroups->childConnections);
        $result = Array();
        foreach($connectionsGroups->childConnections as $item) {

          $item_array = (array) $item;

          $id = $item_array['identifier'];

          $parameters = $this->getRestApi()->get(
              "/api/session/data/{$this->getSetting('backendtype')}/connections/{$id}/parameters",
              null,
              $auth_headers
            );

          // Don't show passwords in Guacamole, as these are plain text
          if (isset($parameters->password))
          {
              $parameters->password = '$encrypted';
          }

          $item_array['parameters'] = (array) $parameters;

          $result[] = (object) $item_array;

        }
        $this->logoutGuacamole($auth_headers['Guacamole-Token']);
        return (array) $result;
    }


    public static function getDefaultKeyColumnName()
    {
        return 'name';
    }

    public function listColumns()
    {
        //if (count($data) == 0){
          $rows = $this->fetchData();
        //}
        //else {
        // $rows = $data;
        //}
        $columns = [];

        foreach ($rows as $object) {
            foreach (array_keys((array) $object) as $column) {
                if (! isset($columns[$column])) {
                    $columns[] = $column;
                }
            }
        }
        //print_r($columns);

        return (array_filter(array_values(array_unique($columns))));
    }


    protected function buildHeaders()
    {
        $headers = [];

        $text = $this->getSetting('headers', '');
        foreach (preg_split('~\r?\n~', $text, -1, PREG_SPLIT_NO_EMPTY) as $header) {
            $header = trim($header);
            $parts = preg_split('~\s*:\s*~', $header, 2);
            if (count($parts) < 2) {
                throw new InvalidPropertyException('Could not parse header: "%s"', $header);
            }

            $headers[$parts[0]] = $parts[1];
        }

        return $headers;
    }

    /**
     * @param QuickForm $form
     * @throws \Zend_Form_Exception
     */
    public static function addSettingsFormFields(QuickForm $form)
    {
        static::addScheme($form);
        static::addSslOptions($form);
        static::addUrl($form);
        static::addBackendType($form);
        static::addAuthentication($form);
        static::addProxy($form);
    }

    /**
     * @param QuickForm $form
     * @throws \Zend_Form_Exception
     */
    protected static function addScheme(QuickForm $form)
    {
        $form->addElement('select', 'scheme', [
            'label' => $form->translate('Protocol'),
            'description' => $form->translate(
                'Whether to use encryption when talking to the REST API'
            ),
            'multiOptions' => [
                'HTTPS' => $form->translate('HTTPS (strongly recommended)'),
                'HTTP'  => $form->translate('HTTP (this is plaintext!)'),
            ],
            'class'    => 'autosubmit',
            'value'    => 'HTTPS',
            'required' => true,
        ]);
    }


    /**
     * @param QuickForm $form
     * @throws \Zend_Form_Exception
     */
    protected static function addSslOptions(QuickForm $form)
    {
        $ssl = ! ($form->getSentOrObjectSetting('scheme', 'HTTPS') === 'HTTP');

        if ($ssl) {
            static::addBoolean($form, 'ssl_verify_peer', [
                'label'       => $form->translate('Verify Peer'),
                'description' => $form->translate(
                    'Whether we should check that our peer\'s certificate has'
                    . ' been signed by a trusted CA. This is strongly recommended.'
                )
            ], 'y');
            static::addBoolean($form, 'ssl_verify_host', [
                'label'       => $form->translate('Verify Host'),
                'description' => $form->translate(
                    'Whether we should check that the certificate matches the'
                    . 'configured host'
                )
            ], 'y');
        }
    }

    /**
     * @param QuickForm $form
     * @throws \Zend_Form_Exception
     */
    protected static function addUrl(QuickForm $form)
    {
        $form->addElement('text', 'url', [
            'label'    => 'Guacamole Base URL',
            'description' => $form->translate(
                'Something like https://guacamole.example.com/guacamole'
            ),
            'required' => true,
        ]);
    }

    /**
     * @param QuickForm $form
     * @throws \Zend_Form_Exception
     */
    protected static function addBackendType(QuickForm $form)
    {
        $form->addElement('text', 'backendtype', [
            'label'    => 'Guacamole backend type (mysql, postgresql)',
            'description' => $form->translate(
                'Something like mysql or postgresql'
            ),
            'required' => true,
        ]);
    }

    /**
     * @param QuickForm $form
     * @throws \Zend_Form_Exception
     */
    protected static function addAuthentication(QuickForm $form)
    {
        $form->addElement('text', 'username', [
            'label' => $form->translate('Username'),
            'description' => $form->translate(
                'Will be used to authenticate against your Guacamole REST API'
            ),
        ]);

        $form->addElement('storedPassword', 'password', [
            'label' => $form->translate('Password'),
        ]);
    }

    /**
     * @param QuickForm $form
     * @throws \Zend_Form_Exception
     */
    protected static function addProxy(QuickForm $form)
    {
        $form->addElement('select', 'proxy_type', [
            'label' => $form->translate('Proxy'),
            'description' => $form->translate(
                'In case your API is only reachable through a proxy, please'
                . ' choose it\'s protocol right here'
            ),
            'multiOptions' => $form->optionalEnum([
                'HTTP'   => $form->translate('HTTP proxy'),
                'SOCKS5' => $form->translate('SOCKS5 proxy'),
            ]),
            'class' => 'autosubmit'
        ]);

        $proxyType = $form->getSentOrObjectSetting('proxy_type');

        if ($proxyType) {
            $form->addElement('text', 'proxy', [
                'label' => $form->translate('Proxy Address'),
                'description' => $form->translate(
                    'Hostname, IP or <host>:<port>'
                ),
                'required' => true,
            ]);
            if ($proxyType === 'HTTP') {
                $form->addElement('text', 'proxy_user', [
                    'label'       => $form->translate('Proxy Username'),
                    'description' => $form->translate(
                        'In case your proxy requires authentication, please'
                        . ' configure this here'
                    ),
                ]);

                $passRequired = strlen($form->getSentOrObjectSetting('proxy_user')) > 0;

                $form->addElement('storedPassword', 'proxy_pass', [
                    'label'    => $form->translate('Proxy Password'),
                    'required' => $passRequired
                ]);
            }
        }
    }

    protected function getUrl()
    {
        $url = $this->getSetting('url');
        $parts = \parse_url($url);
        if (isset($parts['path'])) {
            $path = $parts['path'];
        } else {
            $path = '/';
        }

        if (isset($parts['query'])) {
            $url = "$path?" . $parts['query'];
        } else {
            $url = $path;
        }

        return $url;
    }

    protected function getGuacamoleAuthToken()
    {
      // Login and retrieve token
      $credentials = array('username'=>$this->getSetting('username'),
                           'password'=>$this->getSetting('password'));

      $auth = curl_init();

      // Handle proxy settings
      if ($this->getSetting('proxy', '') != '') {
          curl_setopt($auth, CURLOPT_PROXY, $this->getSetting('proxy'));
          $proxyType = $form->getSentOrObjectSetting('proxy_type');

          if ($proxyType == 'HTTP') {
              curl_setopt($auth, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
              if ($this->getSetting('proxy_user','')!='')
              {
                  curl_setopt($auth, CURLOPT_PROXYUSERPWD,$this->getSetting('proxy_user').':'+$this->getSetting('proxy_pass'));
              }
          }
          elseif ($proxyType == 'SOCKS5') {
              curl_setopt($auth, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
          }

      }

      if ($this->getSetting('ssl_verify_peer', 'y') != 'y') {
          curl_setopt($auth, CURLOPT_SSL_VERIFYPEER, false);
      }

      if ($this->getSetting('ssl_verify_host', 'y') != 'y') {
          curl_setopt($auth, CURLOPT_SSL_VERIFYHOST, false);
      }

      curl_setopt($auth, CURLOPT_URL, $this->getSetting('url')."/api/tokens");
      curl_setopt($auth, CURLOPT_POST, 1);
      curl_setopt($auth, CURLOPT_POSTFIELDS, http_build_query($credentials));
      curl_setopt($auth, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
      curl_setopt($auth, CURLOPT_RETURNTRANSFER, 1);

      $output = curl_exec($auth);
      $info = curl_getinfo($auth);
      curl_close($auth);

      // Check that we are actually able to authenticate
      try {
              $json_data = json_decode($output, TRUE);
              if (isset($json_data['authToken'])){
                  $token = $json_data['authToken'];
              }
              else {
                throw new AuthenticationException('Authentication error when connecting to Guacamole API, check your credentials.');
              }

      } catch (Exception $e) {
        throw new AuthenticationException('Communication error when connecting to Guacamole API, check your configuration.');
      }

      return($token);
    }

    protected function logoutGuacamole($token)
    {

              $auth = curl_init();

              // Handle proxy settings
              if ($this->getSetting('proxy', '') != '') {
                  curl_setopt($auth, CURLOPT_PROXY, $this->getSetting('proxy'));
                  $proxyType = $form->getSentOrObjectSetting('proxy_type');

                  if ($proxyType == 'HTTP') {
                      curl_setopt($auth, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
                      if ($this->getSetting('proxy_user','')!='')
                      {
                          curl_setopt($auth, CURLOPT_PROXYUSERPWD,$this->getSetting('proxy_user').':'+$this->getSetting('proxy_pass'));
                      }
                  }
                  elseif ($proxyType == 'SOCKS5') {
                      curl_setopt($auth, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
                  }

              }

              if ($this->getSetting('ssl_verify_peer', 'y') != 'y') {
                  curl_setopt($auth, CURLOPT_SSL_VERIFYPEER, false);
              }

              if ($this->getSetting('ssl_verify_host', 'y') != 'y') {
                  curl_setopt($auth, CURLOPT_SSL_VERIFYHOST, false);
              }
              curl_setopt($auth, CURLOPT_CUSTOMREQUEST, "DELETE");
              curl_setopt($auth, CURLOPT_URL, $this->getSetting('url')."/api/session");



              curl_setopt($auth, CURLOPT_HTTPHEADER, array(
                  'Guacamole-Token: '.$token
              ));


              $output = curl_exec($auth);
              $info = curl_getinfo($auth);
              curl_close($auth);

              $json_data = json_decode($output, TRUE);

    }

    protected function getRestApi()
    {
        $url = $this->getSetting('url');
        $parts = \parse_url($url);
        if (isset($parts['host'])) {
            $host = $parts['host'];
        } else {
            throw new InvalidArgumentException("URL '$url' has no host");
        }

        $api = new RestApiClient(
            $host,
            null,
            null
        );

        $api->setScheme($this->getSetting('scheme'));
        if (isset($parts['port'])) {
            $api->setPort($parts['port']);
        }

        if ($api->getScheme() === 'HTTPS') {
            if ($this->getSetting('ssl_verify_peer', 'y') === 'n') {
                $api->disableSslPeerVerification();
            }
            if ($this->getSetting('ssl_verify_host', 'y') === 'n') {
                $api->disableSslHostVerification();
            }
        }

        if ($proxy = $this->getSetting('proxy')) {
            if ($proxyType = $this->getSetting('proxy_type')) {
                $api->setProxy($proxy, $proxyType);
            } else {
                $api->setProxy($proxy);
            }

            if ($user = $this->getSetting('proxy_user')) {
                $api->setProxyAuth($user, $this->getSetting('proxy_pass'));
            }
        }

        return $api;
    }

    /**
     * @param QuickForm $form
     * @param string $key
     * @param array $options
     * @param string|null $default
     * @throws \Zend_Form_Exception
     */
    protected static function addBoolean(QuickForm $form, $key, $options, $default = null)
    {
        if ($default === null) {
            $form->addElement('OptionalYesNo', $key, $options);
        } else {
            $form->addElement('YesNo', $key, $options);
            $form->getElement($key)->setValue($default);
        }
    }

    /**
     * @param QuickForm $form
     * @param string $key
     * @param string $label
     * @param string $description
     * @throws \Zend_Form_Exception
     */
    protected static function optionalBoolean(QuickForm $form, $key, $label, $description)
    {
        static::addBoolean($form, $key, [
            'label'       => $label,
            'description' => $description
        ]);
    }
}
