<?php
declare(strict_types=1);
namespace hexydec\ipaddresses;

class progress {

	/**
	 * Outputs a status message to the terminal, overwriting the current line
	 *
	 * @param string $message The status message to display
	 * @return void
	 */
	public static function status(string $message) : void {
		echo "\33[2K\r".$message;
	}

	/**
	 * Renders a progress bar with speed, ETA, memory usage, and optional info labels
	 *
	 * @param int $total The total number of rows to process
	 * @param int $row The current row number
	 * @param array $info Additional labels to display in the progress output
	 * @return void
	 */
	public static function render(int $total, int $row, array $info = []) : void {
		static $start = null;
		static $lastrow = null;
		static $lasttime = null;
		static $i = 0;

		// record timings
		$time = \microtime(true);
		if ($start === null || $row < ($lastrow ?? 0)) {
			$start = $time;
			$lastrow = null;
			$lasttime = null;
			$i = 0;
		}

		// generate progress data
		$spinner = ['|', '/', '-', '\\'];
		$bars = 20;
		$progress = \intval(\round(($bars / $total) * $row));
		$speed = $lastrow === null || $time === $lasttime ? 0 : \intval(($row - $lastrow) / ($time - $lasttime));

		// generate progress bar data
		$items = [
			\number_format($row).' / '.\number_format($total), // rows
			'['.($progress ? \str_repeat('=', $progress - 1).'>' : '').($bars - $progress > 0 ? \str_repeat('-', $bars - $progress) : '').']', // progress bar
			$spinner[$i++ % 4], // spinner
			\round((100 / $total) * $row).'%', // percentage complete
		];

		// average speed overall
		$secs = $time - $start;
		$avg = $secs ? $row / $secs : 0;

		// generate details
		$brackets = \array_merge([
			\number_format($speed).' / '.\number_format($avg).'rps', // speed
			\ltrim(\gmdate('H:i:s', \intval($secs)), '0:').' / '.($row && $secs ? \ltrim(\gmdate('H:i:s', \intval(\round($secs / $row * $total) - \round($secs))), '0:') : '-'),
			self::decorateBytes(\memory_get_usage()).' / '.self::decorateBytes(\memory_get_peak_usage())
		], $info);

		// write to screen
		echo "\33[2K\r".\implode(' ', $items).' ('.\implode(', ', $brackets).')';

		// update values
		$lastrow = $row;
		$lasttime = $time;
	}

	/**
	 * Formats a byte value into a human-readable string with appropriate unit suffix (KB, MB, GB, PB)
	 *
	 * @param string|int|float $field The byte value, or a key to look up in $data
	 * @param array $data An optional data array to look up $field as a key
	 * @param array $config Optional configuration with 'precision' key for decimal places
	 * @return ?string The formatted byte string, or null if the value is null
	 */
	public static function decorateBytes(string|int|float $field, array $data = [], array $config = []) : ?string {
		$value = \is_string($field) ? $data[$field] : $field;
		if ($value !== null) {
			$sizes = [
				1099511627776 => 'PB',
				1073741824 => 'GB',
				1048576 => 'MB',
				1024 => 'KB'
			];
			foreach ($sizes AS $key => $item) {
				if ($value >= $key) {
					return self::formatNumber($value / $key, $config['precision'] ?? 2).$item;
				}
			}
			return self::formatNumber($value).' bytes';
		}
		return null;
	}

	/**
	 * Formats a number with thousand separators, trimming trailing zeros from decimal places
	 *
	 * @param float|int $value The number to format
	 * @param int $precision The number of decimal places
	 * @return string The formatted number string
	 */
	public static function formatNumber(float|int $value, int $precision = 0) : string {
		$num = \number_format($value, $precision);
		if ($precision && \str_contains($num, '.')) {
			$num = \rtrim(\rtrim($num, '0'), '.');
		}
		return $num;
	}
}