<?php

namespace Phinx\Migration\Helper;

use Phinx\Db\Table\Column,
    Phinx\Db\Table;


class CodeGenerator
{
    /**
     * Checks is column equals 'id' in table options
     *
     * @param Table $table
     * @param Column $column
     *
     * @return bool
     */
    public static function isColumnSinglePrimaryKey(Table $table, Column $column)
    {
        $options = $table->getOptions();
        if (isset($options['primary_key']) && in_array('id', $options['primary_key'])) {
            // $this->table('table', array('id'=>false, 'primary_key'=>array('id', 'value'))) case

            return false;
        }

        if ($column->getName() == 'id' && !isset($options['id'])) {

            return true;
        }

        if (isset($options['id']) && $options['id'] == $column->getName()) {

            return true;
        }

        return false;
    }

    /**
     * @param Table $table
     *
     * @return string
     */
    public static function buildTableOptionsString(Table $table)
    {

        if (is_array($table->getIndexes()) && !array_key_exists('PRIMARY', $table->getIndexes())) {
            //account for tables that do not have a primary key
            return "array('id' => false)";
        } else {
            $stringParts = array();
            $options = $table->getOptions();

            foreach ($options as $option => $value) {
                // TODO: move common code or probably replace with something cooler
                // like var_export() but with friendly output

                if (is_numeric($value)) {
                    $string = "'{$option}'=>$value";
                } elseif (is_bool($value)) {
                    $string = $value ? "'{$option}'=>true" : "'{$option}'=>false";
                } elseif (is_array($value)) {
                    $array = [ ];
                    foreach ($value as $element) {
                        $array[] = "'{$element}'";
                    }
                    $string = "'{$option}'=>array(" . implode(", ", $array) . ")";
                } else {
                    $string = "'{$option}'=>'$value'";
                }

                $stringParts[] = $string;
            }

            return "array(" . implode(", ", $stringParts) . ")";
        }
    }

    /**
     * Build options array for third argument in addColumn()
     *
     * @param Column $column
     *
     * @return string
     */
    public static function buildColumnOptionsString(Column $column)
    {
        $options = array('length', 'default', 'null', 'precision', 'scale', 'after', 'update', 'comment', 'values', 'collation');
        $stringParts = array();
        foreach ($options as $option) {
            if ($option === 'length') {
                $method = 'getLimit';
            } else {
                $method = 'get' . ucfirst($option);
            }

            $value = $column->$method();
            if ($value === null) {
                continue;
            }
            if ($option === 'null' && $value === false) {
                continue;
            }

            // TODO: see buildTableOptionsString()
            if (is_numeric($value)) {
                $string = "'{$option}'=>$value";
            } elseif (is_bool($value)) {
                $string = $value ? "'{$option}'=>true" : "'{$option}'=>false";
            } elseif (is_array($value)) {
                // var_export is a bit verbose, but it is safe for strings
                // that conflict with PHP code.
                $value = str_replace("\n", "", var_export($value, true));
                $string = "'{$option}'=>$value";
            } else {
                $string = "'{$option}'=>'$value'";
            }
            $stringParts[] = $string;
        }

        return count($stringParts) > 0 ? "array(".implode(",", $stringParts).")" : "";
    }

    /**
     * Build arguments string for addColumn()
     *
     * @param Column $column
     *
     * @return string
     */
    public static function buildAddColumnArgumentsString(Column $column)
    {
        $args = array(
            "'{$column->getName()}'",
            "'{$column->getType()}'"
        );
        $options = self::buildColumnOptionsString($column);
        if ($options) {
            $args[] = $options;
        }

        return implode(', ', $args);
    }

    public static function buildFkString(Table\ForeignKey $fk)
    {
        $columns = $fk->getColumns();
        if (count($columns) > 1) {
            $columnsDef = 'array('.implode(',', $columns).')';
        } else {
            $columnsDef = "'{$columns[0]}'";
        }
        $refColumns = $fk->getReferencedColumns();
        if (count($refColumns) > 1) {
            $refColumnsDef = 'array('.implode(',', $refColumns).')';
        } else {
            $refColumnsDef = "'{$refColumns[0]}'";
        }

        return "->addForeignKey({$columnsDef}, '{$fk->getReferencedTable()->getName()}', {$refColumnsDef})";
    }

    public static function buildIndexString($index, $name)
    {
        $command =  "array('" . implode("', '", $index['columns']) . "')";

        $command .= ", array('name' => '{$name}'";

        if (isset($index['fulltext']) && $index['fulltext']) {
            $command .= ", 'type' => 'fulltext'";
        }

        if (isset($index['unique']) && $index['unique']) {
            $command .= ", 'unique' => true";
        }

        if (isset($index['limit']) && $index['limit']) {
            $command .= ", 'limit' => {$index['limit']}";
        }

        return $command . ')';
    }
}
