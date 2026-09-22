## DOMPDF Wrapper for Laravel

### Laravel wrapper for [Dompdf HTML to PDF Converter](https://github.com/dompdf/dompdf)

[![Tests](https://github.com/barryvdh/laravel-dompdf/workflows/Tests/badge.svg)](https://github.com/barryvdh/laravel-dompdf/actions)
[![Packagist License](https://img.shields.io/badge/Licence-MIT-blue)](http://choosealicense.com/licenses/mit/)
[![Latest Stable Version](https://img.shields.io/packagist/v/barryvdh/laravel-dompdf?label=Stable)](https://packagist.org/packages/barryvdh/laravel-dompdf)
[![Total Downloads](https://img.shields.io/packagist/dt/barryvdh/laravel-dompdf.svg?label=Downloads)](https://packagist.org/packages/barryvdh/laravel-dompdf)
[![Fruitcake](https://img.shields.io/badge/Powered%20By-Fruitcake-b2bc35.svg)](https://fruitcake.nl/)

## Installation

### Laravel

Require this package in your composer.json and update composer. This will download the package and the dompdf + fontlib libraries also.

    composer require barryvdh/laravel-dompdf

### Lumen

After updating composer add the following lines to register provider in `bootstrap/app.php`

```
$app->register(\Barryvdh\DomPDF\ServiceProvider::class);
```

To change the configuration, copy the config file to your config folder and enable it in `bootstrap/app.php`:

```
$app->configure('dompdf');
```

## Using

You can create a new DOMPDF instance and load a HTML string, file or view name. You can save it to a file, or stream (show in browser) or download.

```php
    use Barryvdh\DomPDF\Facade\Pdf;

    $pdf = Pdf::loadView('pdf.invoice', $data);
    return $pdf->download('invoice.pdf');
```

or use the App container:

```php
    $pdf = App::make('dompdf.wrapper');
    $pdf->loadHTML('<h1>Test</h1>');
    return $pdf->stream();
```

Or use the facade:

You can chain the methods:

```php
    return Pdf::loadFile(public_path().'/myfile.html')->save('/path-to/my_stored_file.pdf')->stream('download.pdf');
```

You can change the orientation and paper size, and hide or show errors (by default, errors are shown when debug is on)

```php
    Pdf::loadHTML($html)->setPaper('a4', 'landscape')->setWarnings(false)->save('myfile.pdf')
```

If you need the output as a string, you can get the rendered PDF with the output() function, so you can save/output it yourself.

Use `php artisan vendor:publish` to create a config file located at `config/dompdf.php` which will allow you to define local configurations to change some settings (default paper etc).
You can also use your ConfigProvider to set certain keys.

### Configuration

The defaults configuration settings are set in `config/dompdf.php`. Copy this file to your own config directory to modify the values. You can publish the config using this command:

```shell
    php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider"
```

You can still alter the dompdf options in your code before generating the pdf using this command:

```php
    Pdf::setOption(['dpi' => 150, 'defaultFont' => 'sans-serif']);
```

Available options and their defaults:

- **rootDir**: "{app_directory}/vendor/dompdf/dompdf"
- **tempDir**: "/tmp" _(available in config/dompdf.php)_
- **fontDir**: "{app_directory}/storage/fonts" _(available in config/dompdf.php)_
- **fontCache**: "{app_directory}/storage/fonts" _(available in config/dompdf.php)_
- **chroot**: "{app_directory}" _(available in config/dompdf.php)_
- **logOutputFile**: "/tmp/log.htm"
- **defaultMediaType**: "screen" _(available in config/dompdf.php)_
- **defaultPaperSize**: "a4" _(available in config/dompdf.php)_
- **defaultFont**: "serif" _(available in config/dompdf.php)_
- **dpi**: 96 _(available in config/dompdf.php)_
- **fontHeightRatio**: 1.1 _(available in config/dompdf.php)_
- **isPhpEnabled**: false _(available in config/dompdf.php)_
- **isRemoteEnabled**: false _(available in config/dompdf.php)_
- **isJavascriptEnabled**: true _(available in config/dompdf.php)_
- **isHtml5ParserEnabled**: true _(available in config/dompdf.php)_
- **allowedRemoteHosts**: null _(available in config/dompdf.php)_
- **isFontSubsettingEnabled**: false _(available in config/dompdf.php)_
- **debugPng**: false
- **debugKeepTemp**: false
- **debugCss**: false
- **debugLayout**: false
- **debugLayoutLines**: true
- **debugLayoutBlocks**: true
- **debugLayoutInline**: true
- **debugLayoutPaddingBox**: true
- **pdfBackend**: "CPDF" _(available in config/dompdf.php)_
- **pdflibLicense**: ""
- **adminUsername**: "user"
- **adminPassword**: "password"
- **artifactPathValidation**: null _(available in config/dompdf.php)_

#### Note: Since 3.x the remote access is disabled by default, to provide more security. Use with caution!

### Tip: UTF-8 support

In your templates, set the UTF-8 Metatag:

    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>

### Tip: Page breaks

You can use the CSS `page-break-before`/`page-break-after` properties to create a new page.

    <style>
    .page-break {
        page-break-after: always;
    }
    </style>
    <h1>Page 1</h1>
    <div class="page-break"></div>
    <h1>Page 2</h1>

### License

This DOMPDF Wrapper for Laravel is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)
