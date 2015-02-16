#Configuration Reference

All available configuration options are listed below with their default values.

``` yaml
#
# SfynxBehatBundle configuration
#
mink:
    base_url: http://www.sfynx.local/
    selenium2: ~
    browser_name: firefox
    
sfynx_tool_behat: 
    servers: [local, dev]
    locales: [en, fr, ar]
    options:
        server: local
        locale: fr
```
