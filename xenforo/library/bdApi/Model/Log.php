<?php

class bdApi_Model_Log extends XenForo_Model
{
    protected static $_logging = 1;

    public function pauseLogging()
    {
        self::$_logging--;
    }

    public function resumeLogging()
    {
        self::$_logging++;
    }

    public function pruneExpired()
    {
        $days = bdApi_Option::get('logRetentionDays');
        $cutoff = XenForo_Application::$time - $days * 86400;

        return $this->_getDb()->query('DELETE FROM xf_bdapi_log WHERE request_date < ?', $cutoff);
    }

    public function logRequest(
        $requestMethod,
        $requestUri,
        array $requestData,
        $responseCode,
        array $responseOutput,
        array $bulkSet = array()
    ) {
        if (self::$_logging < 1) {
            return false;
        }

        $days = bdApi_Option::get('logRetentionDays');
        if ($days == 0) {
            return false;
        }

        $dw = XenForo_DataWriter::create('bdApi_DataWriter_Log');

        $dw->bulkSet($bulkSet);
        if (!isset($bulkSet['client_id'])) {
            $clientId = '';
            if (XenForo_Application::isRegistered('session')) {
                /* @var $session bdApi_Session */
                $session = XenForo_Application::getSession();
                $clientId = $session->getOAuthClientId();
            }
            if (empty($clientId) && !empty($requestData['client_id'])) {
                $clientId = $requestData['client_id'];
            }
            $dw->set('client_id', $clientId);
        }
        if (!isset($bulkSet['user_id'])) {
            $userId = XenForo_Visitor::getUserId();
            if (empty($userId) && !empty($responseOutput['access_token']) && !empty($responseOutput['user_id'])) {
                $userId = $responseOutput['user_id'];
            }
            $dw->set('user_id', $userId);
        }
        if (!isset($bulkSet['ip_address'])) {
            $dw->set('ip_address', isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
        }

        $dw->set('request_date', XenForo_Application::$time);
        $dw->set('request_method', $requestMethod);
        $dw->set('request_uri', $requestUri);
        $dw->set('request_data', $this->_filterData($requestData));
        $dw->set('response_code', $responseCode);
        $dw->set('response_output', $this->_filterData($responseOutput));

        return $dw->save();
    }

    public function getList(array $conditions = array(), array $fetchOptions = array())
    {
        $data = $this->getLogs($conditions, $fetchOptions);
        $list = array();

        foreach ($data as $id => $row) {
            $list[$id] = $row['client_id'];
        }

        return $list;
    }

    public function getLogById($id, array $fetchOptions = array())
    {
        $data = $this->getLogs(array('log_id' => $id), $fetchOptions);

        return reset($data);
    }

    public function getLogs(array $conditions = array(), array $fetchOptions = array())
    {
        $whereConditions = $this->prepareLogConditions($conditions, $fetchOptions);

        $orderClause = $this->prepareLogOrderOptions($fetchOptions);
        $joinOptions = $this->prepareLogFetchOptions($fetchOptions);
        $limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

        $all = $this->fetchAllKeyed($this->limitQueryResults("
				SELECT log.*
				$joinOptions[selectFields]
				FROM `xf_bdapi_log` AS log
				$joinOptions[joinTables]
				WHERE $whereConditions
				$orderClause
				", $limitOptions['limit'], $limitOptions['offset']), 'log_id');

        foreach ($all as &$record) {
            $record['request_data'] = unserialize($record['request_data']);
            $record['response_output'] = unserialize($record['response_output']);
        }

        return $all;
    }

    public function countLogs(array $conditions = array(), array $fetchOptions = array())
    {
        $whereConditions = $this->prepareLogConditions($conditions, $fetchOptions);
        $joinOptions = $this->prepareLogFetchOptions($fetchOptions);

        return $this->_getDb()->fetchOne("
				SELECT COUNT(*)
				FROM `xf_bdapi_log` AS log
				$joinOptions[joinTables]
				WHERE $whereConditions
				");
    }

    public function prepareLogConditions(array $conditions = array(), array $fetchOptions = array())
    {
        $sqlConditions = array();
        $db = $this->_getDb();

        if (isset($conditions['log_id'])) {
            if (is_array($conditions['log_id'])) {
                if (!empty($conditions['log_id'])) {
                    // only use IN condition if the array is not empty (nasty!)
                    $sqlConditions[] = "log.log_id IN (" . $db->quote($conditions['log_id']) . ")";
                }
            } else {
                $sqlConditions[] = "log.log_id = " . $db->quote($conditions['log_id']);
            }
        }

        if (isset($conditions['client_id'])) {
            if (is_array($conditions['client_id'])) {
                if (!empty($conditions['client_id'])) {
                    // only use IN condition if the array is not empty (nasty!)
                    $sqlConditions[] = "log.client_id IN (" . $db->quote($conditions['client_id']) . ")";
                }
            } else {
                $sqlConditions[] = "log.client_id = " . $db->quote($conditions['client_id']);
            }
        }

        if (isset($conditions['user_id'])) {
            if (is_array($conditions['user_id'])) {
                if (!empty($conditions['user_id'])) {
                    // only use IN condition if the array is not empty (nasty!)
                    $sqlConditions[] = "log.user_id IN (" . $db->quote($conditions['user_id']) . ")";
                }
            } else {
                $sqlConditions[] = "log.user_id = " . $db->quote($conditions['user_id']);
            }
        }

        if (isset($conditions['ip_address'])) {
            if (is_array($conditions['ip_address'])) {
                if (!empty($conditions['ip_address'])) {
                    // only use IN condition if the array is not empty (nasty!)
                    $sqlConditions[] = "log.ip_address IN (" . $db->quote($conditions['ip_address']) . ")";
                }
            } else {
                $sqlConditions[] = "log.ip_address = " . $db->quote($conditions['ip_address']);
            }
        }

        if (isset($conditions['request_date'])) {
            if (is_array($conditions['request_date'])) {
                if (!empty($conditions['request_date'])) {
                    // only use IN condition if the array is not empty (nasty!)
                    $sqlConditions[] = "log.request_date IN (" . $db->quote($conditions['request_date']) . ")";
                }
            } else {
                $sqlConditions[] = "log.request_date = " . $db->quote($conditions['request_date']);
            }
        }

        if (isset($conditions['request_method'])) {
            if (is_array($conditions['request_method'])) {
                if (!empty($conditions['request_method'])) {
                    // only use IN condition if the array is not empty (nasty!)
                    $sqlConditions[] = "log.request_method IN (" . $db->quote($conditions['request_method']) . ")";
                }
            } else {
                $sqlConditions[] = "log.request_method = " . $db->quote($conditions['request_method']);
            }
        }

        if (isset($conditions['response_code'])) {
            if (is_array($conditions['response_code'])) {
                if (!empty($conditions['response_code'])) {
                    // only use IN condition if the array is not empty (nasty!)
                    $sqlConditions[] = "log.response_code IN (" . $db->quote($conditions['response_code']) . ")";
                }
            } else {
                $sqlConditions[] = "log.response_code = " . $db->quote($conditions['response_code']);
            }
        }

        if (!empty($conditions['filter'])) {
            if (is_array($conditions['filter'])) {
                $filterQuoted = XenForo_Db::quoteLike($conditions['filter'][0], $conditions['filter'][1], $db);
            } else {
                $filterQuoted = XenForo_Db::quoteLike($conditions['filter'], 'lr', $db);
            }

            $sqlConditions[] = sprintf('(log.ip_address LIKE %1$s OR log.request_uri LIKE %1$s)', $filterQuoted);
        }

        return $this->getConditionsForClause($sqlConditions);
    }

    public function prepareLogFetchOptions(array $fetchOptions = array())
    {
        $selectFields = '';
        $joinTables = '';

        return array(
            'selectFields' => $selectFields,
            'joinTables' => $joinTables
        );
    }

    public function prepareLogOrderOptions(array $fetchOptions = array(), $defaultOrderSql = '')
    {
        $choices = array('request_date' => 'log.request_date');

        return $this->getOrderByClause($choices, $fetchOptions, $defaultOrderSql);
    }

    protected function _filterData(array &$data, $level = 0)
    {
        $filtered = array();

        $i = 0;
        foreach ($data as $key => &$value) {
            $keyFirstChar = substr($key, 0, 1);
            if ($keyFirstChar === '.'
                || $keyFirstChar === '_'
            ) {
                continue;
            }

            $i++;
            if ($i === 2) {
                // only expand the first item in a pure array
                $keys = array_keys($data);
                $isNotNumeric = false;
                foreach ($keys as $_key) {
                    if (!is_numeric($_key)) {
                        $isNotNumeric = true;
                    }
                }
                if (!$isNotNumeric) {
                    $filtered['...'] = count($keys);
                    return $filtered;
                }
            }

            if (is_array($value)) {
                if ($level < 3) {
                    $filtered[$key] = $this->_filterData($value, $level + 1);
                } else {
                    $filtered[$key] = '(array)';
                }
            } else {
                if (is_bool($value)) {
                    $filtered[$key] = $value;
                } elseif (is_numeric($value)) {
                    $filtered[$key] = $value;
                } elseif (is_string($value)) {
                    if (strlen($value) > 0) {
                        $maxLength = 32;
                        if (utf8_strlen($value) > $maxLength) {
                            $filtered[$key] = utf8_substr($value, 0, $maxLength - 1) . '???';
                        } else {
                            $filtered[$key] = $value;
                        }
                    }
                } elseif (is_object($value)) {
                    if ($value instanceof XenForo_Phrase) {
                        $filtered[$key] = sprintf('(phrase:%s)', $value->getPhraseName());
                    } else {
                        $filtered[$key] = sprintf('(object:%s)', get_class($value));
                    }
                } else {
                    $filtered[$key] = '?';
                }
            }
        }

        if ($level === 0) {
            if (isset($data['_controller']) && isset($data['_action'])) {
                $controller = $data['_controller'];
                $controllerPregReplaceCount = 0;
                $controller = preg_replace('/^bdApi_ControllerApi_/', '', $controller, -1, $controllerPregReplaceCount);
                if ($controllerPregReplaceCount === 0) {
                    $controller = str_replace('_ControllerApi_', '/', $controller);
                }

                $filtered['_controller'] = $controller;
                $filtered['_action'] = $data['_action'];
            }

            if (!empty($data['_isApiJob'])) {
                $filtered['_isApiJob'] = true;
            }
        }

        return $filtered;
    }
}
