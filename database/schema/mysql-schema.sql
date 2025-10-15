/*M!999999\- enable the sandbox mode */ 
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pagos_caja_cusco_castigada`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pagos_caja_cusco_castigada` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `lote_id` bigint(20) unsigned DEFAULT NULL,
  `abogado` varchar(200) DEFAULT NULL,
  `region` varchar(120) DEFAULT NULL,
  `agencia` varchar(120) DEFAULT NULL,
  `titular` varchar(180) DEFAULT NULL,
  `dni` varchar(32) DEFAULT NULL,
  `pagare` varchar(80) DEFAULT NULL,
  `moneda` varchar(10) DEFAULT NULL,
  `tipo_de_recuperacion` varchar(80) DEFAULT NULL,
  `condicion` varchar(80) DEFAULT NULL,
  `cartera` varchar(120) DEFAULT NULL,
  `demanda` varchar(80) DEFAULT NULL,
  `fecha_de_pago` date DEFAULT NULL,
  `pago_en_soles` decimal(15,2) DEFAULT NULL,
  `concatenar` varchar(255) DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `pagado_en_soles` decimal(15,2) DEFAULT NULL,
  `gestor` varchar(120) DEFAULT NULL,
  `status` varchar(60) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pp_dni_fecha_idx` (`dni`,`fecha_de_pago`),
  KEY `pp_gestor_status_idx` (`gestor`,`status`),
  KEY `pagos_caja_cusco_castigada_lote_id_index` (`lote_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pagos_caja_cusco_extrajudicial`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pagos_caja_cusco_extrajudicial` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `lote_id` bigint(20) unsigned NOT NULL,
  `region` varchar(255) DEFAULT NULL,
  `agencia` varchar(255) DEFAULT NULL,
  `titular` varchar(255) DEFAULT NULL,
  `dni` varchar(20) DEFAULT NULL,
  `pagare` varchar(40) DEFAULT NULL,
  `moneda` varchar(10) DEFAULT NULL,
  `tipo_de_recuperacion` varchar(255) DEFAULT NULL,
  `condicion` varchar(255) DEFAULT NULL,
  `demanda` varchar(255) DEFAULT NULL,
  `fecha_de_pago` date DEFAULT NULL,
  `pagado_en_soles` decimal(15,2) DEFAULT NULL,
  `monto_pagado` decimal(15,2) DEFAULT NULL,
  `verificacion_de_bpo` varchar(255) DEFAULT NULL,
  `estado_final` varchar(255) DEFAULT NULL,
  `concatenar` varchar(120) DEFAULT NULL,
  `gestor` varchar(255) DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pagos_lotes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pagos_lotes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tipo` varchar(50) NOT NULL,
  `archivo` varchar(255) DEFAULT NULL,
  `usuario_id` bigint(20) unsigned DEFAULT NULL,
  `total_registros` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pagos_lotes_tipo_created_at_index` (`tipo`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pagos_propia`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pagos_propia` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `lote_id` bigint(20) unsigned DEFAULT NULL,
  `dni` varchar(32) DEFAULT NULL,
  `operacion` varchar(64) DEFAULT NULL,
  `entidad` varchar(100) DEFAULT NULL,
  `equipos` varchar(100) DEFAULT NULL,
  `nombre_cliente` varchar(150) DEFAULT NULL,
  `producto` varchar(100) DEFAULT NULL,
  `moneda` varchar(10) DEFAULT NULL,
  `fecha_de_pago` date DEFAULT NULL,
  `monto_pagado` decimal(15,2) DEFAULT NULL,
  `concatenar` varchar(255) DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `pagado_en_soles` decimal(15,2) DEFAULT NULL,
  `gestor` varchar(120) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pagos_propia_dni_index` (`dni`),
  KEY `pagos_propia_operacion_index` (`operacion`),
  KEY `pagos_propia_gestor_index` (`gestor`),
  KEY `pagos_propia_status_index` (`status`),
  KEY `pagos_propia_fecha_de_pago_index` (`fecha_de_pago`),
  KEY `pagos_propia_lote_id_index` (`lote_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

/*M!999999\- enable the sandbox mode */ 
set autocommit=0;
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'2019_12_14_000001_create_personal_access_tokens_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'2025_08_15_164655_create_pagos_lotes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'2025_08_15_164655_create_pagos_propia_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2025_08_15_192105_create_pagos_caja_cusco_castigada_table',1);
commit;
