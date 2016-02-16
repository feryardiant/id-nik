# Indonesia NIK (Nomor Induk Kependudukan)

[![LICENSE](https://img.shields.io/packagist/l/projek-xyz/id-nik.svg?style=flat-square)](LICENSE.md)
[![VERSION](https://img.shields.io/packagist/v/projek-xyz/id-nik.svg?style=flat-square)](https://github.com/projek-xyz/id-nik/releases)

## Install

Via [Composer](https://getcomposer.org/)

```bash
$ composer require projek-xyz/id-nik --prefer-dist
```

## TO-DO

* [ ] Make it configuratble,
* [ ] write Documentation,
* [ ] Write Unit test,
* [ ] _Any idea? PR are very welcome_ :wink:

## Demo

End-point: [idnik.projek.xyz?nik=<your-nik>](http://idnik.projek.xyz)

**Note**:

* You'll got `200 OK` with `{ name: 'Your Name', ... }` response if your nik is found.
* You'll got `500 Server error` with `{ message: <server-error-message> }` response if some thing bad happen.
* You'll got `404 Not found` with `{ message: 'Not found' }` response if your nik is not found.
* You'll got `406 Not Acceptable` with `{ message: <client-error-message> }` response if you:
  * You're not access it via AJAX,
  * Your `Accept` header doesn't have `application/json`,
  * Don't have `?nik=<your-nik>` query string,
  * `<your-nik>` lenght is not 16 characters,
  * `<your-nik>` is not numeric,

## Usage

**SOON**

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CONDUCT](CONDUCT.md) for details.

## Credits

- [KPU](http://data.kpu.go.id/ss8.php) for the data source.

## Disclaimer

- We send and parse each request to its source.
- We DON'T SERVE ANY response from its source.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
