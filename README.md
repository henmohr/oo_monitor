# oo

## Build/Install no Nextcloud

Este repositório já está no formato de app PHP do Nextcloud (não há build de frontend).

Passos básicos:
1) Copie/clone este diretório para `apps/oo_monitor` no servidor do Nextcloud.
2) Ative o app:
```
occ app:enable oo_monitor
```
3) Acesse a página em Admin Settings > OO Monitor.

Opcional (cron interno do Nextcloud):
```
occ background:cron
```

Comando para ajustar o intervalo via occ:
```
occ oo_monitor:set-interval 10
```

### Docker (exemplo)

Se o Nextcloud roda em container, copie o app para dentro do container (ou monte um volume):
```
docker cp /caminho/para/oo_monitor <container>:/var/www/html/apps/oo_monitor
```

Ative o app dentro do container:
```
docker exec -u www-data <container> php occ app:enable oo_monitor
```

Ajustar intervalo via occ:
```
docker exec -u www-data <container> php occ oo_monitor:set-interval 10
```
