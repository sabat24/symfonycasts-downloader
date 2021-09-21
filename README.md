# SymfonyCasts Downloader
Simple php script to download SymfonyCasts courses.

## Installation
```sh
$ composer install
$ cp src/application.ini src/local.ini // Add your credentials
```

## Usage
```sh
$ php download.php
```

### available options

* `-c, --convert-subtitles[]` Convert downloaded subtitles to provided format. Allowed: `srt`
* `-f, --force[]` Download resources even if file exists locally. Allowed: `video`, `script`, `code`, `subtitles`
* `-d, --download[]`  Download only provided resource types. Allowed: `video`, `script`, `code`, `subtitles`

### options examples

Download only subtitles (`-d`) even if they exist locally (`-f`) and convert them to srt format (`-c`)

```sh
$ php download.php -f subtitles -d subtitles -c srt
```

Download only missing scripts (PDFs) and code archives.

```sh
$ php download.php -d script,code
```

or

```sh
$ php download.php --download="script, code"
```
