=== Cluster Varnish ===
Contributors: 42functions, Byte
Tags: varnish, cache
Tested up to: 4.1
Stable tag: 1.2.1

A plugin to combine WordPress and Varnish on the Byte clustered platform.

== Description ==

In cooperation with 42functions Byte developed its own plugin for WordPress. With this extension WordPress sites can run on the superfast Byte Varnish Cluster. The extension allows WordPress to generate the proper caching headers. Also allows the plugin to automatically empty the cache for specific pages after changing an post in WordPress and cache the entire site should be emptied after clicking a special button.

NOTE: This plugin requires knowledge of varnish caching and server administration, it will not work out of the box. We recommend proper testing.

Please follow the tutorial here: https://www.byte.nl/wiki/Varnish_voor_Wordpress


== Installation ==

1. Upload the folder 'varnish-cache' to the '/wp-content/plugins/' directory
2. Activate the plugin through the 'Plugins' menu in WordPress

== Changelog ==


= 1.2.1 =

* Fix: Prevented fatal error in term sibling loop
* Fix: Prevented fatal error in flush all (WPML)
* Fix: Repaired configuration mismatch for general policy flush

= 1.2.0 =

* New: Added 'cache_flush' filter, parameters: keys, cache instance
* New: Added 'cache_flush_all' filter, parameter: (bool)success, cache instance
* New: Added 'cache_form_display' action, parameter: Module
* New: Added 'cache_form_process' filter, parameter: data, Module
* New: Added experimental support for WPML (v 3.1.8.4)

= 1.1.0 =

* Fix: Removed redundent flag in 'Flush all' action
* Fix: Now flushes the additional supplied urls
* New: Added support to exclude pages from caching
* New: Added support to define expire headers
* New: Added support for flushing a particular url
* New: Added support for flushing an entire object structure
* New: Added Author flushing policy