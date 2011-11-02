<?php
/* Handles pagination of various lists (not topics) */
class Paginate {
	/* The current page number. */
	public $current = 1;

	/* The maximum number of items to be displayed. */
	public $limit;
	
	/* The table offset. */
	public $offset;
	
	/* Sets the page and LIMIT string. */
	function __construct($page = null) {
		if(empty($page)) {
			$page = $_GET['p'];
		}
		if ($page < 2 || ! ctype_digit($page)) {
			$this->current = 1;
		} else {
			$this->current = $page;
		}
		$this->limit = ITEMS_PER_PAGE;
		$this->offset = ITEMS_PER_PAGE * ($this->current - 1);
	}
	
	public function limit_sql() {
	}
	
	/* Generates HTML links for navigating between pages. */
	public function navigation($section_name, $num_items_fetched) {
		$output = '';
		if($this->current != 1) {
			$output .= '<li><a href="' . DIR . $section_name . '">Latest</a></li>';
		}
		if($this->current != 1 && $this->current != 2) {
			$newer = $this->current - 1;
			$output .= '<li><a href="' . DIR . $section_name . '/' . $newer . '">Newer</a></li>';
		}
		if($num_items_fetched == ITEMS_PER_PAGE) {
			$older = $this->current + 1;
			$output .= '<li><a href="' . DIR . $section_name . '/' . $older . '">Older</a></li>';
		}
		if( ! empty($output)) {
			echo "\n" . '<ul class="menu">' . $output . "\n" . '</ul>' . "\n";
		}
	}
}
?>