# tina4php-firebird

### Installation
```
composer require tina4stack/tina4php-firebird
```

### Testing with Docker
```
docker run -e "ACCEPT_EULA=Y" -e "SA_PASSWORD=Tina1234!" -p 1433:1433 mcr.microsoft.com/mssql/server:2022-latest
```

```
composer test
```
