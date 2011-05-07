<?php
/**
 * ipNetwork
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

class ipNetwork
{
	public static function ip_version($addr) {
		if (preg_match('/:/', $addr)) {
			return 'IPv6';
		}
		if (preg_match('/\./', $addr)) {
			return 'IPv4';
		}
		return false;
	}

	public static function ipInNetwork($addr, $network) {
		$version = self::ip_version($addr);
		if ($version && $version == self::ip_version($network)) {
			$func = strtolower($version) . '_InNetwork';
			return !self::$func($addr, $network);
		}
		return false;
	}

	public static function ipv4_InNetwork($addr, $network) {
		list($network, $netmask) = explode('/', $network);
		$maskbit = 32 - (int)$netmask;
		return (self::ipv4_to_bin($addr) >> $maskbit) - (self::ipv4_to_bin($network) >> $maskbit);
	}

	public static function ipv6_InNetwork($addr, $network) {
		list($network, $netmask) = explode('/', $network);
		if (!($netmask = (int)$netmask)) {
			$netmask = 128;
		}
		$addr = self::ipv6_to_bin($addr);
		$network = self::ipv6_to_bin($network);
		for ($i=0; $i<4; ++$i) {
			$maskbit = 0;
			if (32 > $netmask) {
				$maskbit = 32 - $netmask;
			}
			if ($compare = ($addr[$i] >> $maskbit) - ($network[$i] >> $maskbit)) {
				return $compare;
			}
			if (($netmask -= 32) <= 0) {
				break;
			}
		}
		return $compare;
	}

	protected static function ipv4_to_bin($addr) {
		$bin = null;
		foreach ((array)explode('.', $addr) as $b) {
			$bin .= ((int)$b < 0x10 ? '0' : '') . dechex($b);
		}
		return hexdec($bin);
	}

	public static function ipv6_to_full_string($addr) {
		$cnt = 0;
		$addr = explode('::', $addr);
		foreach ($addr as &$addr_end_ref) {
			$addr_end_ref = explode(':', $addr_end_ref);
			$cnt += count($addr_end_ref);
		}
		// ipv4 Interchangeability
		if (preg_match('/\./', $addr_end_ref[$len=count($addr_end_ref)-1])) {
			$ip4 = explode('.', $addr_end_ref[$len]);
			$addr_end_ref[$len] = sprintf('%02s%02s', dechex($ip4[0]), dechex($ip4[1]));
			$addr_end_ref[$len+1] = sprintf('%02s%02s', dechex($ip4[2]), dechex($ip4[3]));
			++$cnt;
		}
		foreach ($addr as &$a) {
			foreach ($a as &$value)
				$value = sprintf('%04s', $value);
		}
		if ($cnt < 8) {
			$addr = array_merge($addr[0], array_fill(0, 8 - $cnt, '0000'), $addr[1]);
		} else {
			$addr = $addr[0];
		}
		return join(':', $addr);
	}

	protected static function ipv6_to_bin($addr) {
		$addr = explode(':', self::ipv6_to_full_string($addr));
		$bin = array();
		for ($i=0; $i<8; $i+=2) {
			$bin[] = hexdec($addr[$i] . $addr[$i+1]);
		}
		return $bin;
	}
}
?>
