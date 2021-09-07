

## GUÍA DE CONFIGURACIÓN SCRAPING SECOP

SecopScrap es un script para minería de dados, la información que obtiene este script, es de los procesos públicos, en este script se utilizan herramientas como laravel 5, crawler, google Chrome versión  75.  

## La información que se obtiene es
*	Entidad Estatal
*	Referencia de proceso
*	Descripción
*	Fase Actual
*	Fecha de publicación
*	Fecha de presentación de ofertas
*	Cuantía
*	Estado actual
*	Url link de proceso


## Requerimientos

*	Php 7.3
*	Composer 2.0.1
*	Requiere estar registrado en el portal secop https://community.secop.gov.co/STS/Users/Login/Index

## Instalación:


1-	git clone https://github.com/luisk262/SecopScrap

2-	composer install ó php -d memory_limit=3G /usr/local/bin/composer install (MAC)

3-	php artisan dusk:install

Para que el script funcione correctamente, es necesario ingresar las credenciales de acceso de nuestro secop, en la configuración del mismo, para ello nos dirigimos a la siguiente ruta en nuestro directorio.

- /test/Browser/ExampleText.php

Al inicio del archivo encontrara  las variables $username y $password, allí debe colocar las credenciales de acceso.


## CORRER SCRIPT

 php artisan dusk

