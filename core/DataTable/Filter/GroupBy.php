<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * @category Piwik
 * @package Piwik
 */
namespace Piwik\DataTable\Filter;

use Piwik\DataTable;
use Piwik\DataTable\Filter;

/**
 * DataTable filter that will group DataTable rows together based on the results
 * of a reduce function. Rows with the same reduce result will be summed and merged.
 *
 * NOTE: This filter should never be queued, it must be applied directly on a DataTable.
 *
 * **Basic usage example**
 * 
 *     // group URLs by host
 *     $dataTable->filter('GroupBy', array('label', function ($labelUrl) {
 *         return parse_url($labelUrl, PHP_URL_HOST);
 *     }));
 * 
 * @package Piwik
 * @subpackage DataTable
 * @api
 */
class GroupBy extends Filter
{
    /**
     * The name of the columns to reduce.
     * @var string
     */
    private $groupByColumn;

    /**
     * A callback that modifies the $groupByColumn of each row in some way. Rows with
     * the same reduction result will be added together.
     */
    private $reduceFunction;

    /**
     * Extra parameters to pass to the reduce function.
     */
    private $parameters;

    /**
     * Constructor.
     *
     * @param DataTable $table The DataTable to filter.
     * @param string $groupByColumn The column name to reduce.
     * @param callable $reduceFunction The reduce function. This must alter the `$groupByColumn`
     *                                 columng in some way.
     * @param array $parameters deprecated - use an [anonymous function](http://php.net/manual/en/functions.anonymous.php)
     *                          instead.
     */
    public function __construct($table, $groupByColumn, $reduceFunction, $parameters = array())
    {
        parent::__construct($table);

        $this->groupByColumn = $groupByColumn;
        $this->reduceFunction = $reduceFunction;
        $this->parameters = $parameters;
    }

    /**
     * See [GroupBy](#).
     *
     * @param DataTable $table
     */
    public function filter($table)
    {
        $groupByRows = array();
        $nonGroupByRowIds = array();

        foreach ($table->getRows() as $rowId => $row) {
            // skip the summary row
            if ($rowId == DataTable::ID_SUMMARY_ROW) {
                continue;
            }

            // reduce the group by column of this row
            $groupByColumnValue = $row->getColumn($this->groupByColumn);
            $parameters = array_merge(array($groupByColumnValue), $this->parameters);
            $groupByValue = call_user_func_array($this->reduceFunction, $parameters);

            if (!isset($groupByRows[$groupByValue])) {
                // if we haven't encountered this group by value before, we mark this row as a
                // row to keep, and change the group by column to the reduced value.
                $groupByRows[$groupByValue] = $row;
                $row->setColumn($this->groupByColumn, $groupByValue);
            } else {
                // if we have already encountered this group by value, we add this row to the
                // row that will be kept, and mark this one for deletion
                $groupByRows[$groupByValue]->sumRow($row, $copyMeta = true, $table->getMetadata(DataTable::COLUMN_AGGREGATION_OPS_METADATA_NAME));
                $nonGroupByRowIds[] = $rowId;
            }
        }

        // delete the unneeded rows.
        $table->deleteRows($nonGroupByRowIds);
    }
}