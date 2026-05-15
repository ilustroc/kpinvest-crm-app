-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 15-05-2026 a las 03:30:10
-- Versión del servidor: 11.8.6-MariaDB-log
-- Versión de PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `u480021566_kpinvest_bd`
--
CREATE DATABASE IF NOT EXISTS `u480021566_kpinvest_bd` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `u480021566_kpinvest_bd`;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asignar_clientes`
--

DROP TABLE IF EXISTS `asignar_clientes`;
CREATE TABLE `asignar_clientes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `numdoc` varchar(20) NOT NULL,
  `operacion` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ccd_clientes`
--

DROP TABLE IF EXISTS `ccd_clientes`;
CREATE TABLE `ccd_clientes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `numdoc` varchar(12) NOT NULL,
  `pdf` varchar(500) DEFAULT NULL,
  `cosecha` varchar(100) DEFAULT NULL,
  `link` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes_cuentas`
--

DROP TABLE IF EXISTS `clientes_cuentas`;
CREATE TABLE `clientes_cuentas` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `numdoc` varchar(30) DEFAULT NULL,
  `cuenta` varchar(50) DEFAULT NULL,
  `nombre` varchar(180) DEFAULT NULL,
  `dpto` varchar(80) DEFAULT NULL,
  `operacion` varchar(50) DEFAULT NULL,
  `entidad` varchar(120) DEFAULT NULL,
  `producto` varchar(120) DEFAULT NULL,
  `cosecha` varchar(120) DEFAULT NULL,
  `moneda` varchar(10) DEFAULT NULL,
  `fecha_compra` date DEFAULT NULL,
  `fecha_castigo` date DEFAULT NULL,
  `deuda_capital` decimal(18,2) DEFAULT NULL,
  `interes` decimal(18,2) DEFAULT NULL,
  `deuda_total` decimal(18,2) DEFAULT NULL,
  `direccion` varchar(200) DEFAULT NULL,
  `provincia` varchar(80) DEFAULT NULL,
  `distrito` varchar(80) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cliente_bloqueos`
--

DROP TABLE IF EXISTS `cliente_bloqueos`;
CREATE TABLE `cliente_bloqueos` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `dni` varchar(20) NOT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cna_solicitudes`
--

DROP TABLE IF EXISTS `cna_solicitudes`;
CREATE TABLE `cna_solicitudes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `correlativo` bigint(20) UNSIGNED DEFAULT NULL,
  `nro_carta` varchar(255) NOT NULL,
  `fecha_pago_realizado` date DEFAULT NULL,
  `monto_pagado` decimal(12,2) DEFAULT NULL,
  `observacion` text DEFAULT NULL,
  `dni` varchar(30) NOT NULL,
  `titular` varchar(255) DEFAULT NULL,
  `producto` varchar(255) DEFAULT NULL,
  `operaciones` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`operaciones`)),
  `nota` text DEFAULT NULL,
  `workflow_estado` varchar(255) NOT NULL DEFAULT 'pendiente',
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `pre_aprobado_por` bigint(20) UNSIGNED DEFAULT NULL,
  `pre_aprobado_at` timestamp NULL DEFAULT NULL,
  `aprobado_por` bigint(20) UNSIGNED DEFAULT NULL,
  `aprobado_at` timestamp NULL DEFAULT NULL,
  `rechazado_por` bigint(20) UNSIGNED DEFAULT NULL,
  `rechazado_at` timestamp NULL DEFAULT NULL,
  `motivo_rechazo` text DEFAULT NULL,
  `docx_path` varchar(255) DEFAULT NULL,
  `pdf_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `migrations`
--

DROP TABLE IF EXISTS `migrations`;
CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pagos_lotes`
--

DROP TABLE IF EXISTS `pagos_lotes`;
CREATE TABLE `pagos_lotes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `archivo` varchar(255) DEFAULT NULL,
  `usuario_id` bigint(20) UNSIGNED DEFAULT NULL,
  `total_registros` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pagos_propia`
--

DROP TABLE IF EXISTS `pagos_propia`;
CREATE TABLE `pagos_propia` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `lote_id` bigint(20) UNSIGNED DEFAULT NULL,
  `dni` varchar(32) DEFAULT NULL,
  `operacion` varchar(64) DEFAULT NULL,
  `entidad` varchar(150) DEFAULT NULL,
  `nombre_cliente` varchar(255) DEFAULT NULL,
  `monto_pagado` decimal(15,2) DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `gestor` varchar(120) DEFAULT NULL,
  `cosecha` varchar(100) DEFAULT NULL,
  `cuenta_recaudo` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `promesas_pago`
--

DROP TABLE IF EXISTS `promesas_pago`;
CREATE TABLE `promesas_pago` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `dni` varchar(30) NOT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `operacion` varchar(50) DEFAULT NULL,
  `fecha_promesa` date NOT NULL,
  `fecha_pago` date DEFAULT NULL,
  `monto` decimal(18,2) NOT NULL DEFAULT 0.00,
  `workflow_estado` varchar(20) NOT NULL DEFAULT 'pendiente',
  `tipo` varchar(20) NOT NULL DEFAULT 'parcial',
  `nro_cuotas` int(10) UNSIGNED DEFAULT NULL,
  `monto_convenio` decimal(18,2) DEFAULT NULL,
  `monto_cuota` decimal(18,2) DEFAULT NULL,
  `cuota_dia` tinyint(3) UNSIGNED DEFAULT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'pendiente',
  `nota` varchar(500) DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `pre_aprobado_por` bigint(20) UNSIGNED DEFAULT NULL,
  `pre_aprobado_at` timestamp NULL DEFAULT NULL,
  `nota_preaprobacion` text DEFAULT NULL,
  `aprobado_por` bigint(20) UNSIGNED DEFAULT NULL,
  `aprobado_at` timestamp NULL DEFAULT NULL,
  `nota_aprobacion` text DEFAULT NULL,
  `rechazado_por` bigint(20) UNSIGNED DEFAULT NULL,
  `rechazado_at` timestamp NULL DEFAULT NULL,
  `nota_rechazo` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `promesa_cuotas`
--

DROP TABLE IF EXISTS `promesa_cuotas`;
CREATE TABLE `promesa_cuotas` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `promesa_id` bigint(20) UNSIGNED NOT NULL,
  `nro` int(10) UNSIGNED NOT NULL,
  `fecha` date NOT NULL,
  `monto` decimal(12,2) NOT NULL,
  `es_balon` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `promesa_operaciones`
--

DROP TABLE IF EXISTS `promesa_operaciones`;
CREATE TABLE `promesa_operaciones` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `promesa_id` bigint(20) UNSIGNED NOT NULL,
  `operacion` varchar(50) NOT NULL,
  `cartera` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `role` enum('administrador','supervisor','asesor','sistemas','soporte','usuario') NOT NULL DEFAULT 'usuario',
  `supervisor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `asignar_clientes`
--
ALTER TABLE `asignar_clientes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_asignacion_cliente` (`numdoc`,`operacion`),
  ADD KEY `asignar_clientes_numdoc_index` (`numdoc`),
  ADD KEY `asignar_clientes_operacion_index` (`operacion`);

--
-- Indices de la tabla `ccd_clientes`
--
ALTER TABLE `ccd_clientes`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `clientes_cuentas`
--
ALTER TABLE `clientes_cuentas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cc_numdoc_search` (`numdoc`,`entidad`,`moneda`,`deuda_total`,`deuda_capital`);

--
-- Indices de la tabla `cliente_bloqueos`
--
ALTER TABLE `cliente_bloqueos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cliente_bloqueos_user_id_dni_unique` (`user_id`,`dni`),
  ADD KEY `cliente_bloqueos_dni_index` (`dni`);

--
-- Indices de la tabla `cna_solicitudes`
--
ALTER TABLE `cna_solicitudes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cna_solicitudes_nro_carta_unique` (`nro_carta`),
  ADD KEY `cna_solicitudes_dni_index` (`dni`);

--
-- Indices de la tabla `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `pagos_lotes`
--
ALTER TABLE `pagos_lotes`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `pagos_propia`
--
ALTER TABLE `pagos_propia`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_pagos_lote` (`lote_id`);

--
-- Indices de la tabla `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`);

--
-- Indices de la tabla `promesas_pago`
--
ALTER TABLE `promesas_pago`
  ADD PRIMARY KEY (`id`),
  ADD KEY `promesas_pago_user_id_foreign` (`user_id`),
  ADD KEY `promesas_pago_dni_index` (`dni`),
  ADD KEY `promesas_pago_operacion_index` (`operacion`),
  ADD KEY `promesas_pago_pre_aprobado_por_foreign` (`pre_aprobado_por`),
  ADD KEY `promesas_pago_aprobado_por_foreign` (`aprobado_por`),
  ADD KEY `promesas_pago_rechazado_por_foreign` (`rechazado_por`),
  ADD KEY `promesas_pago_workflow_estado_index` (`workflow_estado`);

--
-- Indices de la tabla `promesa_cuotas`
--
ALTER TABLE `promesa_cuotas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `promesa_cuotas_promesa_id_nro_index` (`promesa_id`,`nro`);

--
-- Indices de la tabla `promesa_operaciones`
--
ALTER TABLE `promesa_operaciones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `promesa_operaciones_promesa_id_operacion_unique` (`promesa_id`,`operacion`),
  ADD KEY `promesa_operaciones_operacion_index` (`operacion`),
  ADD KEY `promesa_operaciones_cartera_index` (`cartera`);

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`),
  ADD KEY `users_supervisor_id_idx` (`supervisor_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `asignar_clientes`
--
ALTER TABLE `asignar_clientes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ccd_clientes`
--
ALTER TABLE `ccd_clientes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `clientes_cuentas`
--
ALTER TABLE `clientes_cuentas`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cliente_bloqueos`
--
ALTER TABLE `cliente_bloqueos`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cna_solicitudes`
--
ALTER TABLE `cna_solicitudes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pagos_lotes`
--
ALTER TABLE `pagos_lotes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pagos_propia`
--
ALTER TABLE `pagos_propia`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `promesas_pago`
--
ALTER TABLE `promesas_pago`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `promesa_cuotas`
--
ALTER TABLE `promesa_cuotas`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `promesa_operaciones`
--
ALTER TABLE `promesa_operaciones`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `cliente_bloqueos`
--
ALTER TABLE `cliente_bloqueos`
  ADD CONSTRAINT `cliente_bloqueos_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `pagos_propia`
--
ALTER TABLE `pagos_propia`
  ADD CONSTRAINT `fk_pagos_propia_lote` FOREIGN KEY (`lote_id`) REFERENCES `pagos_lotes` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `promesas_pago`
--
ALTER TABLE `promesas_pago`
  ADD CONSTRAINT `promesas_pago_aprobado_por_foreign` FOREIGN KEY (`aprobado_por`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `promesas_pago_pre_aprobado_por_foreign` FOREIGN KEY (`pre_aprobado_por`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `promesas_pago_rechazado_por_foreign` FOREIGN KEY (`rechazado_por`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `promesas_pago_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `promesa_cuotas`
--
ALTER TABLE `promesa_cuotas`
  ADD CONSTRAINT `promesa_cuotas_promesa_id_foreign` FOREIGN KEY (`promesa_id`) REFERENCES `promesas_pago` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `promesa_operaciones`
--
ALTER TABLE `promesa_operaciones`
  ADD CONSTRAINT `promesa_operaciones_promesa_id_foreign` FOREIGN KEY (`promesa_id`) REFERENCES `promesas_pago` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
