<?php
/**
 * @author      Wployalty (Ilaiyaraja)
 * @license     http://www.gnu.org/licenses/gpl-2.0.html
 * @link        https://www.wployalty.net
 * */

namespace Wlrm\App\Helper;

use Wlr\App\Helpers\Input;

class Pagination
{
    protected $baseURL = '';
    protected $totalRows = '';
    protected $perPage = 10;
    protected $numLinks = 2;
    protected $currentPage = 0;
    protected $firstLink = '&lsaquo;';
    protected $nextLink = '&raquo;';
    protected $prevLink = '&laquo;';
    protected $lastLink = '&rsaquo;';
    protected $fullTagOpen = '<ul class="pagination">';
    protected $fullTagClose = '</ul>';
    protected $firstTagOpen = '<li>';
    protected $firstTagClose = '</li>';
    protected $lastTagOpen = '<li>';
    protected $lastTagClose = '</li>';
    protected $curTagOpen = '<li class="wlrmg-current-page"><a  href="#">';
    protected $curTagClose = '</a></li>';
    protected $nextTagOpen = '<li>';
    protected $nextTagClose = '</li>';
    protected $prevTagOpen = '<li>';
    protected $prevTagClose = '</li>';
    protected $numTagOpen = '<li>';
    protected $numTagClose = '</li>';
    protected $showCount = true;
    protected $currentOffset = 0;
    protected $queryStringSegment = 'page_number';
    protected $focusId = '';

    /**
     * Pagination constructor.
     *
     * @param array $params
     *
     * @since 1.0.0
     */
    function __construct($params = array())
    {
        if (count($params) > 0) {
            $this->initialize($params);
        }
    }

    /**
     * initialize
     *
     * @param array $params
     *
     * @since 1.0.0
     */
    function initialize($params = array())
    {
        if (count($params) > 0) {
            foreach ($params as $key => $val) {
                if (isset($this->$key)) {
                    $this->$key = $val;
                }
            }
        }
    }

    /**
     * Generate the pagination links
     *
     * @param array $pagination_args
     *
     * @return string
     * @since 1.0.0
     */
    /**
     * Generate the pagination links
     *
     * @param array $pagination_args
     *
     * @return string
     * @since 1.0.0
     */
    function createLinks($pagination_args = array())
    {
        if ($this->totalRows == 0 || $this->perPage == 0) {
            return '';
        }

        $numPages = ceil($this->totalRows / $this->perPage);
        if ($numPages == 1) {
            if ($this->showCount) {
	            $info = sprintf( __( 'Showing: %d', 'wp-loyalty-migration' ), $this->totalRows );
                return ' <div class="dataTables_info">' .$info. '</div>';
            }
            return '';
        }

        $input = new Input();
        $this->currentPage = $input->post_get($this->queryStringSegment, 0);
        if (!is_numeric($this->currentPage) || $this->currentPage == 0) {
            $this->currentPage = 1;
        }

        $baseParams = $_GET; // Retrieve current query parameters
        unset($baseParams[$this->queryStringSegment]); // Remove page_number to dynamically append

        $queryString = http_build_query($baseParams); // Build the query string
        $baseURLWithParams = $this->baseURL . ($queryString ? '?' . $queryString . '&' : '?') . $this->queryStringSegment . '=';

        $output = '';
        $this->numLinks = (int)$this->numLinks;
        if ($this->currentPage > $this->totalRows) {
            $this->currentPage = $numPages;
        }
        $uriPageNum = $this->currentPage;
        $start = (($this->currentPage - $this->numLinks) > 0) ? $this->currentPage - ($this->numLinks - 1) : 1;
        $end = (($this->currentPage + $this->numLinks) < $numPages) ? $this->currentPage + $this->numLinks : $numPages;

        $focusId = (isset($pagination_args['focus_id']) && !empty($pagination_args['focus_id'])) ? "#" . $pagination_args['focus_id'] : $this->focusId;

        if ($this->currentPage > $this->numLinks) {
            $output .= $this->firstTagOpen . '<a href="' . $baseURLWithParams . '1' . $focusId . '">' . $this->firstLink . '</a>' . $this->firstTagClose;
        }

        if ($this->currentPage != 1) {
            $i = ($uriPageNum - 1);
            if ($i == 0) {
                $i = '';
            }
            $output .= $this->prevTagOpen . '<a href="' . $baseURLWithParams . $i . $focusId . '">' . $this->prevLink . '</a>' . $this->prevTagClose;
        }

        for ($loop = $start - 1; $loop <= $end; $loop++) {
            $i = $loop;
            if ($i >= 1) {
                if ($this->currentPage == $loop) {
                    $output .= $this->curTagOpen . $loop . $this->curTagClose;
                } else {
                    $output .= $this->numTagOpen . '<a href="' . $baseURLWithParams . $i . $focusId . '">' . $loop . '</a>' . $this->numTagClose;
                }
            }
        }

        if ($this->currentPage < $numPages) {
            $i = ($this->currentPage + 1);
            $output .= $this->nextTagOpen . '<a href="' . $baseURLWithParams . $i . $focusId . '">' . $this->nextLink . '</a>' . $this->nextTagClose;
        }

        if (($this->currentPage + $this->numLinks) < $numPages) {
            $i = $numPages;
            $output .= $this->lastTagOpen . '<a href="' . $baseURLWithParams . $i . $focusId . '">' . $this->lastLink . '</a>' . $this->lastTagClose;
        }

        $output = preg_replace("#([^:])//+#", "\\1/", $output);
        $output = $this->fullTagOpen . $output . $this->fullTagClose;

        if ($this->showCount) {
            $currentOffset = ($this->currentPage > 1) ? ($this->currentPage - 1) * $this->perPage : $this->currentPage;
            $info = 'Showing ' . $currentOffset . ' to ';
            if (($currentOffset + $this->perPage) <= $this->totalRows) {
                $info .= $this->currentPage * $this->perPage;
            } else {
                $info .= $this->totalRows;
            }
            $info .= ' of ' . $this->totalRows;
            $info = ' <div class="dataTables_info">' . $info . '</div>';
            $output .= $info;
        }

        return $output;
    }

}