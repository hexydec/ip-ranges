# IP Ranges
Automatically updated lists of known IP addresses in IPv4 and IPv6 ranges. Currently offering lists of known datacentre, crawler, and country IP ranges.

## Why?
Detecting whether traffic is coming from a known IP address is useful in many contexts. But the data is quite difficult to come by in a useful form, so this auto-updating repository provides a reliable output to be ingested by other applications.

## What Output is Produced?
Currently the following datasets are available:

- Datacentre IP Ranges (Available as JSON, CSV, and Text)
- Crawler IP Ranges (Available as JSON, CSV, and Text)
- Country IP Ranges (Available as JSON and CSV)

## How can I integrate the data?
You can integrate this project by downloading the desired datasource with the following links:

- Datacentre IP Ranges
	- [As JSON](https://raw.githubusercontent.com/hexydec/ip-ranges/main/output/datacentres.json)
	- [As CSV](https://raw.githubusercontent.com/hexydec/ip-ranges/main/output/datacentres.csv)
	- [As Text](https://raw.githubusercontent.com/hexydec/ip-ranges/main/output/datacentres.txt)
- Crawler IP Ranges
	- [As JSON](https://raw.githubusercontent.com/hexydec/ip-ranges/main/output/crawlers.json)
	- [As CSV](https://raw.githubusercontent.com/hexydec/ip-ranges/main/output/crawlers.csv)
	- [As Text](https://raw.githubusercontent.com/hexydec/ip-ranges/main/output/crawlers.txt)
- Country IP Ranges
	- [As JSON](https://raw.githubusercontent.com/hexydec/ip-ranges/main/output/countries.json)
	- [As CSV](https://raw.githubusercontent.com/hexydec/ip-ranges/main/output/countries.csv)

### Datacentre CSV Format

The datacentre CSV now includes country codes where available, derived from cloud provider region metadata:

```
"Amazon AWS",3.4.12.4/32,ie
"Google Cloud Platform",34.1.208.0/20,za
"Microsoft Azure Public",20.53.1.68/30,au
```

Country codes are provided for Amazon AWS, Google Cloud Platform, Microsoft Azure, and Oracle Cloud ranges. Other providers have an empty third column.

### Country CSV Format

The country CSV maps CIDR ranges to ISO 3166-1 alpha-2 country codes:

```
us,1.0.0.0/24
cn,1.0.1.0/24
au,1.0.4.0/22
```

## How are the IP's collected?

This script uses a number of sources freely available on GitHub and the Web to collect the IP ranges. They are:

### Datacentres
- [Amazon AWS published IP ranges](https://ip-ranges.amazonaws.com/ip-ranges.json)
- [Google Cloud Platform public IP ranges](https://www.gstatic.com/ipranges/cloud.json)
- [Microsoft Azure public IP ranges](https://www.microsoft.com/en-my/download/details.aspx?id=56519)
- [Oracle Cloud public IP ranges](https://docs.oracle.com/en-us/iaas/tools/public_ip_ranges.json)
- [Cloudflare public IP ranges](https://www.cloudflare.com/ips-v4/) ([IPv6](https://www.cloudflare.com/ips-v6/))
- [Linode IP ranges](https://geoip.linode.com/)
- [ASN-IP Project](https://github.com/ipverse/asn-ip) to retrieve the IP ranges for each ASN

**USE AT YOUR OWN RISK! The IP's in the list are only based on ASN allocation, there is no foolproof way of knowing whether users or robots are attached to the IP addresses**

### Crawlers
Reads and scrapes pages of published IP addresses for the following crawlers:

- GoogleBot
- BingBot
- AhrefsBot
- AppleBot
- FacebookBot
- OpenAI Bots
- DuckDuckBot
- Mistral AI
- Perplexity AI
- Claude AI
- OnCrawl Bot
- SiteImprove Bot
- YandexBot
- UptimeRobot
- PingdomBot
- Site24x7

### Countries

Country IP ranges are compiled from multiple sources, listed in order of priority (highest priority last):

1. **Regional Internet Registries (RIRs)** — Official IP allocations from ARIN, RIPE NCC, APNIC, LACNIC, and AfriNIC
2. **BGP Routing Tables** — Active route announcements mapped to country via ASN registration, sourced from [RouteViews](https://www.routeviews.org/)
3. **Geofeeds** — Self-published geolocation data from network operators, sourced from [GeoLocateMuch](https://geolocatemuch.com/)
4. **Cloud Provider Regions** — Authoritative region-to-country mappings from Amazon AWS, Google Cloud Platform, Microsoft Azure, and Oracle Cloud

When the same CIDR block appears in multiple sources, higher priority sources overwrite lower ones. For overlapping ranges of different sizes, the most specific (smallest) block wins.

## How often is the list updated
Daily, if any of the IP ranges have been updated, or new ASN's are captured.

## ASNXXXX or crawler XXXX should be on the list
I am happy to accept suggestions of ASN's that should be captured for the datacentre IP ranges list.

If you wish for the tool to capture crawler IP addresses, please submit an issue with a link to the published list.

## Data Attribution

This project utilizes data provided by [RouteViews](https://www.routeviews.org/). Use of this data is subject to the [CC BY 4.0 license](https://creativecommons.org/licenses/by/4.0/).

> With contributions from network operators and volunteers all over the world, RouteViews collects BGP data by direct peering at Internet Exchange Points (IXPs) or multi-hop peering. Data are archived and made publicly available for download at archive.routeviews.org, lg.routeviews.org, and api.routeviews.org.
