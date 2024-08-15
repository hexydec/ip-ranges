# IP Ranges
Automatically updated lists of known IP addresses in IPv4 and IPv6 ranges. Currently offering a list of known datacentre and crawler IP ranges.

## Why?
Detecting whether traffic is coming from a known IP address is useful in many contexts. But the data is quite difficult to come by in a useful form, so this auto-updating repository provides a reliable output to be ingested by other applications.

## What Output is Produced?
Currently the following datasets are available:

- Datacentre IP Ranges (Available as JSON, CSV, and Text)
- Crawler IP Ranges (Available as JSON, CSV, and Text)

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

## How are the IP's collected?
This script uses a number of sources freely available on GitHub and the Web to collect the IP ranges. They are:

### Datacentres
- [Amazon AWS published IP ranges](https://ip-ranges.amazonaws.com/ip-ranges.json)
- [Google Cloud Platform public IP ranges](https://www.gstatic.com/ipranges/cloud.json)
- [Microsoft Azure public IP ranges](https://www.microsoft.com/en-my/download/details.aspx?id=56519)
- [Cloudflare public IP ranges](https://www.cloudflare.com/ips-v4/) ([IPv6](https://www.cloudflare.com/ips-v6/))
- [ASN-IP project](https://github.com/ipverse/asn-ip) to retrieve the IP ranges for each ASN

Apart from the published ranges from the cloud providers, the ASN names are filtered via a regular expression to capture the ASN's that are obviously hosting companies such as:

- IONOS
- GoDaddy
- Hetzner
- LiquidWeb
- DigitalOcean
- SqaureSpace
- OVH
- SiteGround
- Rackspace
- Namecheap
- Linode
- Dedipower
- Pulsant
- MediaTemple
- Valice
- Akamai
- Fly.io

And more ...

There will be more ASN's that provide hosting services that are not captured, but this is not a bad start. I am doing more work on categorising the ASN providers list.

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
- OnCrawl Bot
- SiteImprove Bot
- YandexBot
- UptimeRobot
- PingdomBot
- AmazonBot

## How often is the list updated
Daily, if any of the IP ranges have been updated, or new ASN's are captured.

## ASNXXXX or crawler XXXX should be on the list
I am happy to accept suggestions of ASN's that should be captured for the datacentre IP ranges list.

If you wish for the tool to capture crawler IP addresses, please submit an issue with a link to the published list.