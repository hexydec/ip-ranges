<?php
declare(strict_types=1);
namespace hexydec\ipaddresses;

class crawlers extends generate {

	public function compile(?string $cache = null) : \Generator {
		$map = [
			[
				'name' => 'GoogleBot',
				'source' => 'https://developers.google.com/static/search/apis/ipranges/googlebot.json',
				'domain' => 'googlebot.com',
				'url' => 'http://www.google.com/bot.html'
			],
			[
				'name' => 'GoogleBot Other',
				'source' => 'https://developers.google.com/static/search/apis/ipranges/special-crawlers.json',
				'domain' => 'google.com',
				'url' => 'http://www.google.com/bot.html'
			],
			[
				'name' => 'GoogleBot User Triggered',
				'source' => 'https://developers.google.com/static/search/apis/ipranges/user-triggered-fetchers.json',
				'domain' => 'gae.googleusercontent.com',
				'url' => 'http://www.google.com/bot.html'
			],
			[
				'name' => 'GoogleBot Cloud Fetch',
				'source' => 'https://developers.google.com/static/search/apis/ipranges/user-triggered-fetchers-google.json',
				'domain' => 'google.com',
				'url' => 'http://www.google.com/bot.html'
			],
			[
				'name' => 'BingBot',
				'source' => 'https://www.bing.com/toolbox/bingbot.json',
				'domain' => 'bing.com',
				'url' => 'http://www.bing.com/bingbot.htm'
			],
			[
				'name' => 'AhrefsBot',
				'source' => 'https://api.ahrefs.com/v3/public/crawler-ip-ranges',
				'domain' => 'ahrefs.com',
				'url' => 'http://ahrefs.com/robot/'
			],
			[
				'name' => 'AppleBot',
				'source' => 'https://search.developer.apple.com/applebot.json',
				'domain' => 'apple.com',
				'url' => 'http://www.apple.com/go/applebot'
			],
			[
				'name' => 'OpenAI Search Bot',
				'source' => 'https://openai.com/searchbot.json',
				'domain' => 'openai.com',
				'url' => 'https://openai.com/searchbot'
			],
			[
				'name' => 'ChatGTP User',
				'source' => 'https://openai.com/chatgpt-user.json',
				'domain' => 'openai.com',
				'url' => 'https://openai.com/bot'
			],
			[
				'name' => 'GPTBot',
				'source' => 'https://openai.com/gptbot.json',
				'domain' => 'openai.com',
				'url' => 'https://openai.com/gptbot'
			]
		];
		foreach ($map AS $item) {
			foreach ($this->getFromJson($item['source'], $cache) AS $value) {
				yield [
					'name' => $item['name'],
					'range' => $value,
					'domain' => $item['domain'],
					'url' => $item['url'] ?? null
				];
			}
		}
		$map = [
			[
				'name' => 'DuckDuckBot',
				'source' => 'https://duckduckgo.com/duckduckgo-help-pages/results/duckduckbot/',
				'domain' => 'duckduckgo.com',
				'url' => 'http://duckduckgo.com/duckduckbot.html'
			],
			[
				'name' => 'OnCrawl',
				'source' => 'https://help.oncrawl.com/en/articles/2288662-what-ips-does-oncrawl-use-to-crawl-a-website',
				'domain' => 'oncrawl.com',
				'url' => 'http://www.oncrawl.com/'
			],
			[
				'name' => 'SiteImprove',
				'source' => 'https://help.siteimprove.com/support/solutions/articles/80000448553-what-ip-addresses-and-user-agents-are-used-by-siteimprove-',
				'domain' => 'siteimprove.com'
			],
			[
				'name' => 'YandexBot',
				'source' => 'https://yandex.com/ips',
				'domain' => 'yandex.com',
				'url' => 'http://yandex.com/bots'
			],
			[
				'name' => 'Site27x7 Site Monitor',
				'source' => 'https://www.site24x7.com/multi-location-web-site-monitoring.html',
				'domain' => 'site24x7.com'
			],
			[
				'name' => 'Claude AI',
				'source' => 'https://docs.anthropic.com/en/api/ip-addresses',
				'domain' => 'anthropic.com'
			],
			[
				'name' => 'SiteImprove',
				'source' => 'https://help.siteimprove.com/support/solutions/articles/80000448553',
				'domain' => 'siteimprove.com',

			]
		];
		foreach ($map AS $item) {
			foreach ($this->getFromHtml($item['source'], $cache) AS $value) {
				yield [
					'name' => $item['name'],
					'range' => $value,
					'domain' => $item['domain'],
					'url' => $item['url'] ?? null
				];
			}
		}
		$map = [
			[
				'name' => 'UptimeRobot',
				'source' => 'https://uptimerobot.com/inc/files/ips/IPv4andIPv6.txt',
				'domain' => 'uptimerobot.com',
				'url' => 'http://www.uptimerobot.com/'
			],
			[
				'name' => 'PingdomBot',
				'source' => 'https://my.pingdom.com/probes/ipv4',
				'domain' => 'pingdom.com',
				'url' => 'http://www.pingdom.com/'
			],
			[
				'name' => 'PingdomBot',
				'source' => 'https://my.pingdom.com/probes/ipv6',
				'domain' => 'pingdom.com',
				'url' => 'http://www.pingdom.com/'
			]
		];
		foreach ($map AS $item) {
			foreach ($this->getFromText($item['source'], $cache) AS $value) {
				yield [
					'name' => $item['name'],
					'range' => $value,
					'domain' => $item['domain'],
					'url' => $item['url'] ?? null
				];
			}
		}
		$map = [
			[
				'name' => 'Meta Crawlers',
				'source' => 'https://www.facebook.com/peering/geofeed',
				'domain' => 'meta.com',
				'url' => 'https://developers.facebook.com/docs/sharing/webmasters/web-crawlers'
			]
		];
		foreach ($map AS $item) {
			foreach ($this->getFromCsv($item['source'], $cache) AS $value) {
				yield [
					'name' => $item['name'],
					'range' => $value,
					'domain' => $item['domain'],
					'url' => $item['url'] ?? null
				];
			}
		}
	}
}