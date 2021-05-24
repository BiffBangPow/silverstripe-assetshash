# silverstripe-assetshash

Usage:

```php
Requirements::set_backend(BBP_Backend::create());
Requirements::css('build/bundle.css', 'screen', ['addhash' => true]);
Requirements::javascript('build/main.bundle.js', ['type' => false, 'addhash' => true]);
```