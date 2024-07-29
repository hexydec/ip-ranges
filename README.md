# Datacentre IP Ranges
Automatically updated list of known datacentre IP addresses in IPv4 and IPv6 ranges.

## Why?
Detecting whether traffic is coming from a datacentre by IP address is useful in many contexts. But the data is quite difficult to come by in a useful form, and not very easy to work out.

## How are the IP's collected?
This script uses a number of sources freely available on GitHub and the Web to collect the IP ranges. They are:

- Amazon AWS published IP ranges
- Google Cloud Platform public IP ranges
- Microsoft Azure public IP ranges
- Cloudflare public IP ranges
- ASN-IP project to retrieve the IP ranges for each ASN

Apart from the published ranges from the cloud providers, the ASN names are filtered via a regular expression to capture the ASN's that are obviously hosting companies. There will be more ASN's that provide hosting services that are not captured, but this is not a bad start.

## ASNXXXX should be on the list
I am happy to accept suggestions of ASN's that should be captured.