<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Notificaciones del navegador

La campana del estudiante muestra los avisos que guarda Laravel. Para habilitar la entrega con la pestaña cerrada, instala las dependencias, aplica las migraciones y genera las claves desde `backend`.

```sh
composer install
php artisan migrate
php artisan webpush:claves
```

Agrega las claves al `.env` junto con `WEB_PUSH_SUBJECT`, usando una URL HTTPS o un correo de contacto en formato `mailto:`. En desarrollo local también puedes usar la URL de Laravel en localhost. Conserva la misma pareja de claves para las suscripciones existentes. Si usas caché de configuración, vuelve a generarla después de modificar el entorno.

Inicia el proceso de la cola con `php artisan queue:work --queue=web-push`. El estudiante puede pulsar **Activar avisos del navegador** en la campana después de iniciar sesión. La aplicación debe servirse por HTTPS o desde `localhost`.

Si OpenSSL de XAMPP requiere el archivo de configuración, define `OPENSSL_CONF` con la ruta a `openssl.cnf` en la terminal desde la que generas las claves e inicias el proceso de la cola.

## Pago con tarjeta mediante Stripe

El botón de tarjeta se habilita con una cuenta de Stripe Payments de un país admitido. Configura `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET` y `STRIPE_FRONTEND_URL` en el entorno del backend. El arancel se toma del período académico y se cobra en BOB. Usa claves `sk_test_…` para pruebas en un entorno Laravel `local` o `testing` y reserva las claves `sk_live_…` para producción.

CPD define el importe en **Parámetros y arancel** y consulta las transacciones en **Pagos con tarjeta**. Checkout usa ese importe al crear cada sesión, por lo que no hace falta crear un producto o precio en el panel de Stripe.

En Stripe configura el endpoint HTTPS `/api/pagos/stripe/webhook` con los eventos `checkout.session.completed` y `checkout.session.expired`. Para pruebas locales, reenvíalos con `stripe listen --events checkout.session.completed,checkout.session.expired --forward-to localhost:8000/api/pagos/stripe/webhook` y usa el secreto que muestra Stripe CLI. La solicitud se actualiza cuando Laravel recibe la confirmación firmada.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
