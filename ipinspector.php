<?php
/**
 * ipInspector
 *
 * The MIT License
 *
 * Copyright(c) 2011 Takehito Gondo
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
include('ipnetwork.php');
class ipInspector extends ipNetwork
{
	private $settings = array(
		'updated_interval' => 2592000,
		'default_action' => 'allow',
		'ipv4_nested' => 1,
		'ipv6_nested' => 2,
	);
	private $iana_server = array(
		'server_url' => 'http://ftp.apnic.net',
		'delegated_urls' => array(
			'apnic'   => '/stats/apnic/delegated-apnic-latest',
			'ripencc' => '/stats/ripe-ncc/delegated-ripencc-latest',
			'lacnic'  => '/stats/lacnic/delegated-lacnic-latest',
			'arin'    => '/stats/arin/delegated-arin-latest',
			'afrinic' => '/stats/afrinic/delegated-afrinic-latest',
			'iana'    => '/stats/iana/delegated-iana-latest',
		),
	);
	private $cached_registries = array(
		'apnic',
		'ripencc',
		'lacnic',
		'arin',
		'afrinic'
	);
	private $header_text = array(
		100 => 'Continue',
		101 => 'Switching Protocols',
		102 => 'Processing',

		200 => 'OK',
		201 => 'Created',
		202 => 'Accepted',
		203 => 'Non-Authoritative Information',
		204 => 'No Content',
		205 => 'Reset Content',
		206 => 'Partial Content',
		207 => 'Multi-Status',
		226 => 'IM Used',

		300 => 'Multiple Choices',
		301 => 'Moved Permanently',
		302 => 'Found',
		303 => 'See Other',
		304 => 'Not Modified',
		305 => 'Use Proxy',
		306 => 'Reserved',
		307 => 'Temporary Redirect',

		400 => 'Bad Request',
		401 => 'Unauthorized',
		402 => 'Payment Required',
		403 => 'Forbidden',
		404 => 'Not Found',
		405 => 'Method Not Allowed',
		406 => 'Not Acceptable',
		407 => 'Proxy Authentication Required',
		408 => 'Request Timeout',
		409 => 'Conflict',
		410 => 'Gone',
		411 => 'Length Required',
		412 => 'Precondition Failed',
		413 => 'Request Entity Too Large',
		414 => 'Request-URI Too Long',
		415 => 'Unsupported Media Type',
		416 => 'Requested Range Not Satisfiable',
		417 => 'Expectation Failed',
		422 => 'Unprocessable Entity',
		423 => 'Locked',
		424 => 'Failed Dependency',
		426 => 'Upgrade Required',

		500 => 'Internal Server Error',
		501 => 'Not Implemented',
		502 => 'Bad Gateway',
		503 => 'Service Unavailable',
		504 => 'Gateway Timeout',
		505 => 'HTTP Version Not Supported',
		506 => 'Variant Also Negotiates',
		507 => 'Insufficient Storage',
		510 => 'Not Extended'
	);
	private $filters = array(
		'registry_filters' => array(),
		'country_filters' => array(),
		'ipv4_filters' => array(),
		'ipv6_filters' => array(),
	);
	private $cache;
	private $cls_dir;
	private $actions;

	public function __construct($settings=null) {
		$this->cls_dir = dirname(__FILE__);
		if (is_readable($this->cls_dir.'/config.php')) {
			include($this->cls_dir.'/config.php');
		}
		$this->settings = array_merge((array)$this->settings, (array)$default_settings, (array)$settings);
		foreach (array('cached_registries', 'header_text') as $var_name) {
			$this->{$var_name} = array_merge((array)$this->{$var_name}, (array)$$var_name);
		}
		if (!empty($iana_server['server_url'])) {
			$this->iana_server['server_url'] = $iana_server['server_url'];
		}
		foreach ((array)$this->cached_registries as $registry) {
			if (!empty($iana_server['delegated_urls'][$registry])) {
				$this->iana_server['delegated_urls'][$registry] = $iana_server['delegated_urls'][$registry];
			}
		}
		if (empty($this->settings['cache_dir'])) {
			$this->settings['cache_dir'] = $this->cls_dir . '/cache';
		}
		if (!file_exists($this->settings['cache_dir'])) {
			mkdir($this->settings['cache_dir'], true);
		}
	}

	public function __destruct() {
		if (!is_null($this->cache)) {
			unset($this->cache);
		}
	}

	private function initialize() {
		$cache_info_path = $this->settings['cache_dir'] .'/cache_info';
		if ($is_update = time() - (int)@filectime($cache_info_path) <= $this->settings['updated_interval']) {
			$this->cache_info = json_decode(file_get_contents($cache_info_path));
			$ipv4_nested = $this->cache_info->ipv4_nested == $this->settings['ipv4_nested'];
			$ipv6_nested = $this->cache_info->ipv6_nested == $this->settings['ipv6_nested'];
		}
		if (!$is_update || !$ipv4_nested || !$ipv6_nested) {
			$this->load_delegated_list();
			$this->cache_info = json_decode(file_get_contents($cache_info_path));
		}
	}

	public function destroy() {
		$this->__destruct();
	}

	public function inspection($addr=null, $filters=array()) {
		$this->initialize();
		if ($addr_info = $this->catch_addr($addr ? $addr : $_SERVER['REMOTE_ADDR'])) {
			$addr_info->filter = $this->do_filters($addr_info, $filters);
		}
		$this->destroy();
		return $addr_info;
	}

	public function catch_addr($addr) {
		// current_visitor ?
		if ($addr == $_SERVER['REMOTE_ADDR']) {
			// is_proxy
		}
		// build cache
		$version = strtolower(self::ip_version($addr));
		$ip = $version == 'ipv6' ?
			explode(':', self::ipv6_to_full_string($addr)):
			explode('.', $addr);
		$cachename = $this->settings['cache_dir'] .'/'. $version .'_'. join('.', array_slice($ip, 0, $this->cache_info->{$version.'_nested'})) .'.json';
		if (!file_exists($cachename)) {
			return false;
		}
		$this->cache = json_decode(file_get_contents($cachename));
		// search
		$addr_info = $this->bsearch($addr, $this->cache, 0, count($this->cache) -1, array('ipNetwork', $version.'_InNetwork'));
		if ($addr_info) {
			$addr_info->addr = $addr;
		}
		return $addr_info;
	}

	public function do_filters($addr_info, $filters) {
		$filter = null;
		$registry_filters = array_merge((array)$this->filters['registry_filters'], (array)$filters['registry_filters']);
		$country_filters = array_merge((array)$this->filters['country_filters'], (array)$filters['country_filters']);
		foreach (array('registry', 'country') as $filter_name) {
			$filter_tmp = ${$filter_name.'_filters'};
			if (array_key_exists($addr_info->{$filter_name}, $filter_tmp)) {
				$filter = array(
					'filter_type' => $filter_name.'_filters',
					'filter_key' => $addr_info->{$filter_name},
					'filter_value' => $filter_tmp[$addr_info->{$filter_name}],
				);
			}
		}
		$version = strtolower(self::ip_version($addr_info->addr));
		$addr_merged = $this->merge_addr_filters($filters['addr_filters']);
		$addr_filters = $addr_merged[$version];
		if ($addr_filter = $this->bsearch($addr_info->addr, $addr_filters, 0, count($addr_filters) -1, array('ipNetwork', $version.'_InNetwork'))) {
			$filter = array(
				'filter_type' => 'addr_filters',
				'filter_key' => $addr_filter->cidr,
				'filter_value' => $addr_filter->filter,
			);
		}

		ob_start();
		$header =& $filter['filter_value']['header'];
		$action =& $filter['filter_value']['action'];
		$action = $this->do_action('pre_action', (is_null($action)? $this->settings['default_action']:$action), $filter, $addr_info);
		$header = $this->do_action($action, $header, $filter, $addr_info);
		$output = ob_get_contents();
		ob_end_clean();

		if (!empty($header)) {
			$this->send_header($header);
		}
		if (!empty($output)) {
			echo $output;
		}
		return $this->do_action('post_action', $filter, $addr_info);
	}

	private function do_action($action_name, $action_arg) {
		$args = array_slice(func_get_args(), 2);
		$code = null;
		if ($len = count($args)) {
			$code = array();
			for ($i=0; $i<$len; ++$i) {
				$code[] = '$args['.$i.']';
			}
			$code = ','. join(',', $code);
		}
		if (array_key_exists($action_name, (array)$this->actions)) {
			return eval('return call_user_func($this->actions[$action_name], $action_arg'. $code .');');
		}
		if (method_exists($this, '_default_action_'. $action_name)) {
			return eval('return $this->_default_action_'. $action_name .'($action_arg'. $code .');');
		}
		return $action_arg;
	}

	public function add_filters($filter_name, $filters) {
		if ($filter_name == 'addr_filters') {
			$merged = $this->merge_addr_filters($filters);
			$this->filters['ipv4_filters'] = $merged['ipv4'];
			$this->filters['ipv6_filters'] = $merged['ipv6'];
		} else {
			$this->filters[$filter_name] = array_merge((array)$this->filters[$filter_name], (array)$filters);
		}
	}

	private function merge_addr_filters($filters) {
		$ipv4 = array();
		$ipv6 = array();
		foreach ((array)$filters as $cidr => $filter) {
			$version = strtolower(self::ip_version($cidr));
			if ($version == 'ipv4' || $version == 'ipv6') {
				${$version}[] = (object)array('cidr' => $cidr, 'filter' => (array)$filter);
			}
		}
		foreach (array('ipv4', 'ipv6') as $version) {
			$this->qsort($$version, 0, count($$version) -1, array($this, $version.'_cmp'));
			$$version = $this->msort((array)$this->filters[$version.'_filters'], $$version, array($this, $version.'_cmp'));
		}
		return compact('ipv4', 'ipv6');
	}

	public function add_action($action_name, $callback) {
		if (is_callable($callback)) {
			$this->actions[$action_name] = $callback;
		}
	}

	private function load_delegated_list() {
		system('rm '. $this->settings['cache_dir'] .'/*');
		if ($fp = fopen($this->settings['cache_dir'] .'/cache_info', 'w')) {
			fwrite($fp, json_encode(array(
				'ipv4_nested' => $this->settings['ipv4_nested'],
				'ipv6_nested' => $this->settings['ipv6_nested']
			)));
			fclose($fp);

			foreach ($this->cached_registries as $registry) {
				$temp = array();
				$bufs = (array)@file($this->iana_server['server_url'].'/'.$this->iana_server['delegated_urls'][$registry]);
				// $bufs = file($registry);
				foreach ($bufs as &$buf) {
					if (preg_match('/^'. $registry .'\|[A-Z]{2}\|(ipv4|ipv6)/', $buf)) {
						list(, $country, $version, $network, $range, $updated, $status) = explode('|', trim($buf));
						$ip = explode(($version=='ipv6'?':':'.'), preg_replace('/[.:]+$/', '', $network));
						$nested = array_splice($ip, 0, $this->settings[$version.'_nested']);
						$prefix = $version .'_'. join('_', $nested);
						if ($old_prefix && $old_prefix != $prefix) {
							$old_prefix = explode('_', $old_prefix);
							$this->save(
								$this->settings['cache_dir'].'/'.$old_prefix[0].'_'.join('.', array_slice($old_prefix, 1)).'.json',
								$temp, array($this, $old_prefix[0].'_cmp')
							);
							$temp = array();
						}
						$old_prefix = $prefix;
						if ($version == 'ipv4') {
							for ($netmask=32; $range > 1; $range /= 2) --$netmask;
							$range = $netmask;
						}
						$cidr = $network .'/'. $range;
						$temp[] = (object)compact('cidr', 'country', 'registry');
					}
				}
				$this->save(
					$this->settings['cache_dir'].'/'.$version.'_'.join('.', $nested).'.json',
					$temp, array($this, $version.'_cmp')
				);
				unset($temp, $bufs);
			}
		}
	}

	private function save($filename, $value, $merge_cmp) {
		$filedir = dirname($filename);
		if (!file_exists($filedir)) {
			mkdir($filedir, true);
		}
		if (file_exists($filename)) {
			$value = $this->msort(json_decode(file_get_contents($filename)), $value, $merge_cmp);
		}
		if ($fp = fopen($filename, 'w')) {
			fwrite($fp, json_encode($value));
			fclose($fp);
		}
	}

	public static function bsearch($ip, $array, $head, $tail, $cmp) {
		if ($head > $tail) return false;
		$tg = (int)(($head + $tail) / 2);

		$ret = call_user_func($cmp, $ip, $array[$tg]->cidr);
		if ($ret < 0) {
			return self::bsearch($ip, $array, $head, $tg - 1, $cmp);
		}
		if ($ret > 0) {
			return self::bsearch($ip, $array, $tg + 1, $tail, $cmp);
		}
		return $array[$tg];
	}

	public static function msort($m_array, $n_array, $cmp) {
		$m_len = count($m_array);
		$n_len = count($n_array);
		$m_cnt = 0;
		$n_cnt = 0;
		$new = array();

		while ($m_len - $m_cnt > 0 && $n_len - $n_cnt > 0) {
			$ret = call_user_func($cmp, $m_array[$m_cnt], $n_array[$n_cnt]);
			if ($ret == 0) {
				$new[] = $m_array[$m_cnt++];
				++$n_cnt;
			} else if ($ret < 0) {
				$new[] = $m_array[$m_cnt++];
			} else if ($ret > 0) {
				$new[] = $n_array[$n_cnt++];
			}
		}

		if ($m_len - $m_cnt == 0) {
			$ptr =& $n_array;
			$len = $n_len;
			$cnt = $n_cnt;
		} else {
			$ptr =& $m_array;
			$len = $m_len;
			$cnt = $m_cnt;
		}
		for ($i=$cnt; $i<$len; ++$i) {
			$new[] = $ptr[$i];
		}
		return $new;
	}

	public static function qsort(&$array, $head, $tail, $cmp) {
		if($head >= $tail) return;

		$pvt = $head;
		for($i = $head + 1; $i <= $tail; $i++) {
			$ret = call_user_func($cmp, $array[$i], $array[$head]);
			if ($ret < 0) {
				self::swap($array[++$pvt], $array[$i]);
			}
		}
		self::swap($array[$head], $array[$pvt]);
		self::qsort($array, $head, $pvt - 1, $cmp);
		self::qsort($array, $pvt + 1, $tail, $cmp);
	}

	public static function ipv4_cmp($m_array, $n_array) {
		$m = explode('.', substr($m_array->cidr, 0, strrpos($m_array->cidr, '/')));
		$n = explode('.', substr($n_array->cidr, 0, strrpos($n_array->cidr, '/')));
		for ($i=0; $i<4; ++$i) {
			if ($ret = (int)$m[$i] - (int)$n[$i]) {
				return $ret;
			}
		}
		return $ret;
	}

	public static function ipv6_cmp($m_array, $n_array) {
		$m = explode(':', substr($m_array->cidr, 0, strrpos($m_array->cidr, '/')));
		$n = explode(':', substr($n_array->cidr, 0, strrpos($n_array->cidr, '/')));
		$m = array_merge($m, (array)@array_fill(0, 8 - count($m), '0'));
		$n = array_merge($n, (array)@array_fill(0, 8 - count($n), '0'));
		for ($i=0; $i<8; ++$i) {
			if ($ret = hexdec($m[$i]) - hexdec($n[$i])) {
				return $ret;
			}
		}
		return $ret;
	}

	public static function swap(&$m, &$n) {
		$tmp = $m;
		$m = $n;
		$n = $tmp;
	}

	private function send_header($status) {
		$code = abs((int)$status);
		$text = $this->status_text[$code];

		$protocol = $_SERVER['SERVER_PROTOCOL'];
		if ('HTTP/1.1' != $protocol && 'HTTP/1.0' != $protocol) {
			$protocol = 'HTTP/1.0';
		}
		@header("$protocol $code $text", true, $code);
	}

	// default actions
	function _default_action_deny($header, $filter, $addr_info) {
		if (empty($header)) $header = 403;
		if (file_exists($this->cls_dir.'/template/'. $header .'.html')) {
			echo file_get_contents($this->cls_dir.'/template/'. $header .'.html');
		}
		return $header;
	}
	function _default_action_post_action($filter, $addr_info) {
		if ($filter['filter_value']['action'] == 'deny' && $filter['filter_value']['header'] == 403) {
			die;
		}
		return $filter;
	}
}
?>
