<?php
declare(strict_types=1);
namespace hexydec\ipaddresses;

class crawlers extends generate {

	/**
	 * Compiles crawler/bot IP ranges from JSON, HTML, text, and CSV sources for known search engines and monitoring services
	 *
	 * @param ?string $cache The directory to cache downloaded data, or null to skip caching
	 * @return \Generator Yields associative arrays with 'name', 'range', 'domain', and 'url' keys
	 */
	public function compile(?string $cache = null) : \Generator {
		$map = [
			[
				'name' => 'GoogleBot Common Crawlers',
				'source' => 'https://developers.google.com/static/search/apis/ipranges/googlebot.json',
				'domain' => 'googlebot.com',
				'url' => 'http://www.google.com/bot.html',
				'match' => 'GoogleBot,Googlebot-Image,Googlebot-Video,Googlebot-News,Storebot-Google,Google-InspectionTool,GoogleOther,GoogleOther-Image,GoogleOther-Video,Google-CloudVertexBot,Google-Extended'
			],
			[
				'name' => 'GoogleBot Special Case Crawlers',
				'source' => 'https://developers.google.com/static/search/apis/ipranges/special-crawlers.json',
				'domain' => 'google.com',
				'url' => 'http://www.google.com/bot.html',
				'match' => 'APIs-Google,AdsBot-Google-Mobile,AdsBot-Google,Mediapartners-Google,Google-Safety,AdsBot-Google-Mobile,DuplexWeb-Google,Googlebot-Image,AdsBot-Google-Mobile-Apps,googleweblight'
			],
			[
				'name' => 'GoogleBot User Triggered Fetchers',
				'source' => 'https://developers.google.com/static/search/apis/ipranges/user-triggered-fetchers.json',
				'domain' => 'gae.googleusercontent.com',
				'url' => 'http://www.google.com/bot.html',
				'match' => 'FeedFetcher-Google,GoogleProducer,google-speakr,Google-Read-Aloud,Google-Site-Verification'
			],
			[
				'name' => 'GoogleBot User Triggered Fetchers Google',
				'source' => 'https://developers.google.com/static/search/apis/ipranges/user-triggered-fetchers-google.json',
				'domain' => 'google.com',
				'url' => 'http://www.google.com/bot.html',
				'match' => 'Google-CWS,FeedFetcher-Google,Google-Agent,GoogleMessages,Google-NotebookLM,Google-Pinpoint,GoogleProducer,Google-Read-Aloud,Google-Site-Verification'
			],
			[
				'name' => 'BingBot',
				'source' => 'https://www.bing.com/toolbox/bingbot.json',
				'domain' => 'bing.com',
				'url' => 'http://www.bing.com/bingbot.htm',
				'match' => 'Bingbot,AdIdxBot,MicrosoftPreview'
			],
			[
				'name' => 'AhrefsBot',
				'source' => 'https://api.ahrefs.com/v3/public/crawler-ip-ranges',
				'domain' => 'ahrefs.com',
				'url' => 'http://ahrefs.com/robot/',
				'match' => 'AhrefsBot'
			],
			[
				'name' => 'AppleBot',
				'source' => 'https://search.developer.apple.com/applebot.json',
				'domain' => 'apple.com',
				'url' => 'http://www.apple.com/go/applebot',
				'match' => 'Applebot'
			],
			[
				'name' => 'OAI-SearchBot',
				'source' => 'https://openai.com/searchbot.json',
				'domain' => 'openai.com',
				'url' => 'https://openai.com/searchbot',
				'match' => 'OAI-SearchBot'
			],
			[
				'name' => 'ChatGPT-User',
				'source' => 'https://openai.com/chatgpt-user.json',
				'domain' => 'openai.com',
				'url' => 'https://openai.com/bot',
				'match' => 'ChatGPT-User'
			],
			[
				'name' => 'GPTBot',
				'source' => 'https://openai.com/gptbot.json',
				'domain' => 'openai.com',
				'url' => 'https://openai.com/gptbot',
				'match' => 'GPTBot'
			],
			[
				'name' => 'DuckDuck Bot',
				'source' => 'https://duckduckgo.com/duckduckbot.json',
				'domain' => 'duckduckgo.com',
				'url' => 'https://duckduckgo.com/duckduckgo-help-pages/results/duckduckbot/',
				'match' => 'DuckDuckBot'
			],
			[
				'name' => 'Mistral AI',
				'source' => 'https://mistral.ai/mistralai-user-ips.json',
				'domain' => 'mistral.ai',
				'url' => 'https://docs.mistral.ai/robots/',
				'match' => 'MistralAI-User'
			],
			[
				'name' => 'Perplexity AI',
				'source' => 'https://www.perplexity.ai/perplexitybot.json',
				'domain' => 'perplexity.ai',
				'url' => 'https://docs.perplexity.ai/guides/bots',
				'match' => 'PerplexityBot'
			]
		];
		foreach ($map AS $item) {
			progress::status('Fetching '.$item['name'].' ranges');
			foreach ($this->getFromJson($item['source'], $cache) AS $value) {
				yield [
					'name' => $item['name'],
					'range' => $value,
					'domain' => $item['domain'],
					'url' => $item['url'] ?? null,
					'match' => $item['match']
				];
			}
		}
		$map = [
			[
				'name' => 'OnCrawl',
				'source' => 'https://help.oncrawl.com/en/articles/2288662-what-ips-does-oncrawl-use-to-crawl-a-website',
				'domain' => 'oncrawl.com',
				'url' => 'http://www.oncrawl.com/',
				'match' => 'CCBot'
			],
			[
				'name' => 'YandexBot',
				'source' => 'https://yandex.com/ips',
				'domain' => 'yandex.com',
				'url' => 'http://yandex.com/bots',
				'match' => 'YandexBot,YandexImages,YandexRenderResourcesBot'
			],
			[
				'name' => 'Site24x7',
				'source' => 'https://www.site24x7.com/multi-location-web-site-monitoring.html',
				'domain' => 'site24x7.com',
				'url' => 'https://www.site24x7.com',
				'match' => 'Site24x7'
			],
			[
				'name' => 'ClaudeBot',
				'source' => 'https://docs.anthropic.com/en/api/ip-addresses',
				'domain' => 'anthropic.com',
				'url' => 'https://www.anthropic.com',
				'match' => 'ClaudeBot'
			],
			[
				'name' => 'SiteImprove',
				'source' => 'https://help.siteimprove.com/support/solutions/articles/80000448553',
				'domain' => 'siteimprove.com',
				'url' => 'https://siteimprove.com',
				'match' => 'Probe by Siteimprove.com,LinkCheck by Siteimprove.com,SiteCheck-sitecrawl by Siteimprove.com,Image size by Siteimprove.com'
			],
			[
				'name' => 'Add Search Bot',
				'source' => 'https://www.addsearch.com/docs/indexing/whitelisting-addsearch-bot/',
				'domain' => 'addsearch.com',
				'url' => 'https://addsearch.com',
				'match' => 'AddSearchBot'
			]
		];
		foreach ($map AS $item) {
			progress::status('Fetching '.$item['name'].' ranges');
			foreach ($this->getFromHtml($item['source'], $cache) AS $value) {
				yield [
					'name' => $item['name'],
					'range' => $value,
					'domain' => $item['domain'],
					'url' => $item['url'] ?? null,
					'match' => $item['match']
				];
			}
		}
		$map = [
			[
				'name' => 'UptimeRobot',
				'source' => 'https://uptimerobot.com/inc/files/ips/IPv4andIPv6.txt',
				'domain' => 'uptimerobot.com',
				'url' => 'http://www.uptimerobot.com/',
				'match' => 'UptimeRobot'
			],
			[
				'name' => 'Pingdom',
				'source' => 'https://my.pingdom.com/probes/ipv4',
				'domain' => 'pingdom.com',
				'url' => 'http://www.pingdom.com/',
				'match' => 'Pingdom.com_bot_version_1.4,PingdomTMS'
			],
			[
				'name' => 'Pingdom',
				'source' => 'https://my.pingdom.com/probes/ipv6',
				'domain' => 'pingdom.com',
				'url' => 'http://www.pingdom.com/',
				'match' => 'Pingdom.com_bot_version_1.4,PingdomTMS'
			]
		];
		foreach ($map AS $item) {
			progress::status('Fetching '.$item['name'].' ranges');
			foreach ($this->getFromText($item['source'], $cache) AS $value) {
				yield [
					'name' => $item['name'],
					'range' => $value,
					'domain' => $item['domain'],
					'url' => $item['url'] ?? null,
					'match' => $item['match']
				];
			}
		}
		$map = [
			[
				'name' => 'Meta Crawlers',
				'source' => 'https://www.facebook.com/peering/geofeed',
				'domain' => 'meta.com',
				'url' => 'https://developers.facebook.com/docs/sharing/webmasters/web-crawlers',
				'match' => 'facebookexternalhit,facebookcatalog,meta-externalagent,meta-externalfetcher'
			]
		];
		foreach ($map AS $item) {
			progress::status('Fetching '.$item['name'].' ranges');
			foreach ($this->getFromCsv($item['source'], $cache) AS $value) {
				yield [
					'name' => $item['name'],
					'range' => $value[0],
					'domain' => $item['domain'],
					'url' => $item['url'] ?? null,
					'match' => $item['match']
				];
			}
		}
	}
}