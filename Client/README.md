# Persisting Printing in DOMjudge database/Client

## Install deps

```bash
apt update
apt install php-cli php-curl
```

## Start Client

First you need to change some configurations in `config.php`, such as `balloon_password`, `balloon_endpoint`, and I believe you should be able to know what they mean from the variable names.

```bash
# start printclient
./printclient ${your printer name}
```
