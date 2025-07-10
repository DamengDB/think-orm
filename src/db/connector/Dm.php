<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: dengze <dengze@dameng.com>
// +----------------------------------------------------------------------

namespace think\db\connector;

use PDO;
use think\db\BaseQuery;
use think\db\Connection;
use think\db\exception\PDOException;
use think\db\PDOConnection;
use think\db\ConnectionInterface;

/**
 * Dm数据库驱动
 */
// class Dm extends Connection
class Dm extends PDOConnection
{
    protected $version = '3.0.1';

    protected $params = [
        PDO::ATTR_CASE              => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE           => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS      => PDO::NULL_NATURAL,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::ATTR_EMULATE_PREPARES  => false,
    ];

    /**
     * 解析pdo连接的dsn信息
     * @access protected
     * @param array $config 连接信息
     * @return string
     */
    protected function parseDsn(array $config): string
    {
         $dsn = 'dm:host=';
         if (!empty($config['hostname'])) {
             $dsn .=  $config['hostname'] . ($config['hostport'] ? ':' . $config['hostport'] : '');
         }
        $this->params[PDO::CHARSET] =  $config['charset'];
        return $dsn;
    }

    /**
     * 连接数据库方法.
     *
     * @param array      $config         连接参数
     * @param int        $linkNum        连接序号
     * @param array|bool $autoConnection 是否自动连接主数据库（用于分布式）
     *
     * @throws PDOException
     *
     * @return PDO
     */
    public function connect(array $config = [], $linkNum = 0, $autoConnection = false): PDO
    {
        if (isset($this->links[$linkNum])) {
            return $this->links[$linkNum];
        }

        if (empty($config)) {
            $config = $this->config;
        } else {
            $config = array_merge($this->config, $config);
        }

        if (!empty($config['break_match_str'])) {
            $this->breakMatchStr = array_merge($this->breakMatchStr, (array) $config['break_match_str']);
        }

        try {
            if (empty($config['dsn'])) {
                $config['dsn'] = $this->parseDsn($config);
            }

            // 连接参数
            if (isset($config['params']) && is_array($config['params'])) {
                $params = $config['params'] + $this->params;
            } else {
                $params = $this->params;
            }

            // 记录当前字段属性大小写设置
            $this->attrCase = $params[PDO::ATTR_CASE];


            $startTime = microtime(true);

            $this->links[$linkNum] = $this->createPdo($config['dsn'], $config['username'], $config['password'], $params);

            // SQL监控
            if (!empty($config['trigger_sql'])) {
                $this->trigger('CONNECT:[ UseTime:' . number_format(microtime(true) - $startTime, 6) . 's ] ' . $config['dsn']);
            }

            return $this->links[$linkNum];
        } catch (\PDOException $e) {
            if ($autoConnection) {
                $this->db->log($e->getMessage(), 'error');

                return $this->connect($autoConnection, $linkNum);
            } else {
                throw $e;
            }
        }
    }

    /**
     * 取得数据表的字段信息
     * @access public
     * @param string $tableName
     * @return array
     */
    public function getFields(string $tableName): array
    {
        list($tableName) = explode(' ', $tableName);

        if (str_contains($tableName, '.')) {
            [$schema, $name] = explode('.', $tableName);
        }
        else
        {
            $name = $tableName;
        }

        $sql = "select a.column_name,data_type,DECODE(nullable, 'Y', 0, 1) notnull, data_default, DECODE (A.column_name,b.column_name,1,0) pk, DECODE (INFO2 & 0x01, 0x01, 1, 0)auto_in from all_tab_columns a,(select column_name from all_constraints c, all_cons_columns col where c.constraint_name = col.constraint_name and c.constraint_type = 'P' and c.table_name = '". $name . "' ) b,(select sc.name,sc.info2 from sys.vsyscolumns sc, sys.vsysobjects so where sc.id = so.id and so.name = '". $name . "' ) c where table_name = '". $name . "' and a.column_name = c.name and a.column_name = b.column_name (+)";

        $pdo    = $this->getPDOStatement($sql);
        $result = $pdo->fetchAll(PDO::FETCH_ASSOC);

        $info   = [];

        if ($result) {
            foreach ($result as $key => $val) {
                $val = array_change_key_case($val);
                $info[$val['column_name']] = [
                    'name'    => $val['column_name'],
                    'type'    => $val['data_type'],
                    'notnull' => $val['notnull'],
                    'default' => $val['data_default'],
                    'primary' => $val['pk'],
                    'autoinc' => $val['auto_in'],
                ];
            }
        }

        return $this->fieldCase($info);
    }

    /**
     * 取得数据库的表信息（暂时实现取得用户表信息）
     * @access   public
     * @param string $dbName
     * @return array
     */
    public function getTables(string $dbName = ''): array
    {
        $sql    = 'select table_name from all_tables';
        $pdo    = $this->getPDOStatement($sql);
        $result = $pdo->fetchAll(PDO::FETCH_ASSOC);
        $info   = [];

        foreach ($result as $key => $val) {
            $info[$key] = current($val);
        }

        return $info;
    }

    protected function supportSavepoint(): bool
    {
        return true;
    }
}

