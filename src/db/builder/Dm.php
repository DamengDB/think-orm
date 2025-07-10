<?php
declare (strict_types = 1);
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: dengze <dengze@dameng.com>
// +----------------------------------------------------------------------

namespace think\db\builder;

use think\db\Builder;
use think\db\BaseQuery as Query;
use think\db\exception\DbException as Exception;
use think\db\Raw;
/**
 * Dm数据库驱动
 */
class Dm extends Builder
{
    protected $selectSql = 'SELECT%DISTINCT%%EXTRA%%FORCE% %FIELD% FROM %TABLE%%JOIN%%WHERE%%GROUP%%HAVING%%UNION%%ORDER%%LIMIT% %LOCK%%COMMENT%';

    /**
     * 设置锁机制
     * @access protected
     * @param  Query      $query 查询对象
     * @param  bool|false $lock
     * @return string
     */
    protected function parseLock(Query $query, $lock = false): string
    {
        if (!$lock) {
            return '';
        }

        return ' FOR UPDATE NOWAIT ';
    }

    /**
     * index分析，可在操作链中指定需要强制使用的索引.
     *
     * @param Query $query 查询对象
     * @param mixed $index
     *
     * @return string
     */
    protected function parseForce(Query $query, string | array $index): string
    {
        if (empty($index)) {
            return '';
        }

        if (is_array($index)) {
            throw new Exception('not support');
        }

        $index = $this->parseKey($query,$index);

        $options = $query->getOptions();

        $table = $this->parseTable($query, $options['table']);

        return sprintf(' /*+INDEX(%s, %s)*/ ', $table, $index);
    }

    /**
     * 字段和表名处理
     * @access public
     * @param  Query  $query  查询对象
     * @param  string $key
     * @param  string $strict
     * @return string
     */
    public function parseKey(Query $query, string|int|Raw $key, bool $strict = false): string
    {
        if (is_int($key)) {
            return (string) $key;
        } elseif ($key instanceof Raw) {
            return $this->parseRaw($query, $key);
        }

        $key = trim($key);

        if (str_contains($key, '->') && !str_contains($key, '(')) {
            // JSON字段支持
            [$field, $name] = explode('->', $key);

            return 'json_value(' . $this->parseKey($query, $field, true) . ', \'$' . (str_starts_with($name, '[') ? '' : '.') . str_replace('->', '.', $name) . '\')';

        } elseif (str_contains($key, '.') && !preg_match('/[,\'\"\(\)\[\s]/', $key)) {
            [$table, $key] = explode('.', $key, 2);

            $alias = $query->getOptions('alias');

            if ('__TABLE__' == $table) {
                $table = $query->getOptions('table');
                $table = is_array($table) ? array_shift($table) : $table;
            }

            if (isset($alias[$table])) {
                $table = $alias[$table];
            }
        }

        if ($strict && !preg_match('/^[\w\.\*\x{4e00}-\x{9fa5}]+$/u', $key)) {
            throw new Exception('not support data:' . $key);
        }

        if ('*' != $key && !preg_match('/[,\'\"\*\(\)\[.\s]/', $key)) {
            $key = '"' . $key . '"';
        }

        if (isset($table)) {
            if (str_contains($table, '.')) {
                $table = str_replace('.', '"."', $table);
            }

            $key = '"' . $table . '".' . $key;
        }

        return $key;
    }

    /**
     * 随机排序
     * @access protected
     * @param  Query $query 查询对象
     * @return string
     */
    protected function parseRand(Query $query): string
    {
        return 'DBMS_RANDOM.value';
    }
}

