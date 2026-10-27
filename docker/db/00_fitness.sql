/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.20-13.0.2-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: fitness
-- ------------------------------------------------------
-- Server version	13.0.2-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;

--
-- Table structure for table `bienestar_plan_semanal`
--

DROP TABLE IF EXISTS `bienestar_plan_semanal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bienestar_plan_semanal` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `semana_inicio` date NOT NULL COMMENT 'Lunes de la semana',
  `completados` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT '{}' COMMENT 'JSON {"Lunes":true,...}' CHECK (json_valid(`completados`)),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuario_semana` (`id_usuario`,`semana_inicio`),
  CONSTRAINT `fk_bps_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Plan semanal de bienestar (checklist por día)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bienestar_plan_semanal`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `bienestar_plan_semanal` WRITE;
/*!40000 ALTER TABLE `bienestar_plan_semanal` DISABLE KEYS */;
INSERT INTO `bienestar_plan_semanal` VALUES
(1,1,'2026-09-28','[]','2026-10-01 22:56:41'),
(3,2,'2026-10-12','[]','2026-10-13 21:50:31'),
(5,3,'2026-10-12','[]','2026-10-14 12:31:02'),
(6,4,'2026-10-12','[]','2026-10-16 13:03:33');
/*!40000 ALTER TABLE `bienestar_plan_semanal` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `clinico_cita`
--

DROP TABLE IF EXISTS `clinico_cita`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `clinico_cita` (
  `id_cita` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `tipo_consulta` enum('general','justificante','vacunacion','laboratorio','seguimiento','urgencia_menor') NOT NULL DEFAULT 'general',
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `estado` enum('pendiente','confirmada','cancelada','atendida') NOT NULL DEFAULT 'pendiente',
  `notas_usuario` text DEFAULT NULL,
  `notas_medico` text DEFAULT NULL,
  `diagnostico` text DEFAULT NULL COMMENT 'Solo el médico puede llenar esto',
  `folio` varchar(12) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_cita`),
  UNIQUE KEY `uq_folio` (`folio`),
  KEY `idx_usuario` (`id_usuario`),
  KEY `idx_fecha` (`fecha`),
  CONSTRAINT `fk_cc_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clinico_cita`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `clinico_cita` WRITE;
/*!40000 ALTER TABLE `clinico_cita` DISABLE KEYS */;
/*!40000 ALTER TABLE `clinico_cita` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `clinico_evaluacion_sintomas`
--

DROP TABLE IF EXISTS `clinico_evaluacion_sintomas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `clinico_evaluacion_sintomas` (
  `id_evaluacion` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `sintomas` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Array de síntomas seleccionados' CHECK (json_valid(`sintomas`)),
  `dias_duracion` varchar(30) DEFAULT NULL,
  `nivel_urgencia` enum('verde','amarillo','rojo','emergencia') NOT NULL,
  `orientacion` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_evaluacion`),
  KEY `idx_usuario` (`id_usuario`),
  KEY `idx_usuario_urgencia` (`id_usuario`,`nivel_urgencia`),
  CONSTRAINT `fk_ces_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clinico_evaluacion_sintomas`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `clinico_evaluacion_sintomas` WRITE;
/*!40000 ALTER TABLE `clinico_evaluacion_sintomas` DISABLE KEYS */;
/*!40000 ALTER TABLE `clinico_evaluacion_sintomas` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `clinico_signos_vitales`
--

DROP TABLE IF EXISTS `clinico_signos_vitales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `clinico_signos_vitales` (
  `id_signo` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `temperatura_c` decimal(4,1) DEFAULT NULL,
  `frecuencia_cardiaca` smallint(5) unsigned DEFAULT NULL COMMENT 'bpm',
  `presion_sistolica` smallint(5) unsigned DEFAULT NULL COMMENT 'mmHg',
  `presion_diastolica` smallint(5) unsigned DEFAULT NULL COMMENT 'mmHg',
  `saturacion_o2` tinyint(3) unsigned DEFAULT NULL COMMENT '%',
  `frecuencia_resp` tinyint(3) unsigned DEFAULT NULL COMMENT 'resp/min',
  `glucosa_mg_dl` smallint(5) unsigned DEFAULT NULL,
  `interpretacion` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Semáforo generado por backend' CHECK (json_valid(`interpretacion`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_signo`),
  KEY `idx_usuario_fecha` (`id_usuario`,`fecha`),
  KEY `idx_usuario_fecha_hora` (`id_usuario`,`fecha`,`hora`),
  CONSTRAINT `fk_csv_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clinico_signos_vitales`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `clinico_signos_vitales` WRITE;
/*!40000 ALTER TABLE `clinico_signos_vitales` DISABLE KEYS */;
/*!40000 ALTER TABLE `clinico_signos_vitales` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `consentimiento_informado`
--

DROP TABLE IF EXISTS `consentimiento_informado`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `consentimiento_informado` (
  `id_consentimiento` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `version` varchar(10) NOT NULL DEFAULT '1.0',
  `acepto` tinyint(1) NOT NULL DEFAULT 0,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_consentimiento`),
  KEY `idx_usuario` (`id_usuario`),
  CONSTRAINT `fk_ci_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `consentimiento_informado`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `consentimiento_informado` WRITE;
/*!40000 ALTER TABLE `consentimiento_informado` DISABLE KEYS */;
/*!40000 ALTER TABLE `consentimiento_informado` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `cuestionario_habitos`
--

DROP TABLE IF EXISTS `cuestionario_habitos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cuestionario_habitos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `minutos_semana` varchar(20) NOT NULL COMMENT 'menos_75 | 75_150 | 150_300 | mas_300',
  `frecuencia_semana` varchar(10) NOT NULL COMMENT 'nunca | 1_2 | 3_4 | 5_mas',
  `tipo_actividad` varchar(20) NOT NULL COMMENT 'aerobico | fuerza | flexibilidad | ninguna',
  `lugar_ejercicio` varchar(20) NOT NULL COMMENT 'casa | gimnasio | exterior | ninguno',
  `sexo` varchar(15) DEFAULT NULL,
  `peso` decimal(5,1) DEFAULT NULL,
  `altura` decimal(5,1) DEFAULT NULL,
  `imc` decimal(4,1) DEFAULT NULL,
  `avatar` varchar(20) DEFAULT NULL,
  `motivacion` varchar(20) NOT NULL COMMENT 'salud | estetica | rendimiento | medico',
  `barrera` varchar(20) NOT NULL COMMENT 'tiempo | motivacion | salud | instalaciones',
  `condicion_fisica` varchar(15) NOT NULL COMMENT 'muy_buena | buena | regular | mala',
  `horas_sedentario` varchar(10) NOT NULL COMMENT 'menos_4 | 4_6 | 6_8 | mas_8',
  `condicion_salud` varchar(15) NOT NULL COMMENT 'ninguna | moderada | limitante | prefiero_no',
  `condicion_medica` varchar(30) DEFAULT NULL,
  `dolor_frecuente` varchar(20) DEFAULT NULL,
  `horas_sueno` varchar(15) DEFAULT NULL,
  `hidratacion` varchar(15) DEFAULT NULL,
  `nutricion` varchar(25) NOT NULL COMMENT 'muy_equilibrada | bastante_equilibrada | poco_equilibrada | no_considero',
  `tipo_dieta` varchar(20) DEFAULT NULL,
  `comidas_dia` varchar(10) DEFAULT NULL,
  `objetivo_nutricional` varchar(30) DEFAULT NULL,
  `calentamiento` varchar(15) NOT NULL COMMENT 'siempre | a_veces | casi_nunca | nunca',
  `entorno_social` varchar(15) NOT NULL COMMENT 'motivan | acompañan | indiferente | sin_apoyo',
  `nivel_estres` varchar(15) DEFAULT NULL,
  `estado_animo` varchar(15) DEFAULT NULL,
  `fitness_score` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Score 0-100',
  `nivel_recomendado` varchar(15) NOT NULL COMMENT 'principiante | intermedio | avanzado',
  `rutinas_sugeridas` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Array de slugs de rutinas recomendadas' CHECK (json_valid(`rutinas_sugeridas`)),
  `fecha_respuesta` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `parq_corazon` enum('no','si') DEFAULT NULL,
  `parq_dolor_pecho` enum('no','si') DEFAULT NULL,
  `parq_mareo` enum('no','si') DEFAULT NULL,
  `parq_cronica` enum('no','si') DEFAULT NULL,
  `parq_medicamento` enum('no','si') DEFAULT NULL,
  `parq_huesos` enum('no','si') DEFAULT NULL,
  `parq_supervision` enum('no','si') DEFAULT NULL,
  `parq_resultado` enum('apto','consultar') DEFAULT NULL,
  `ipaq_vig_dias` varchar(10) DEFAULT NULL,
  `ipaq_vig_min` varchar(10) DEFAULT NULL,
  `ipaq_mod_dias` varchar(10) DEFAULT NULL,
  `ipaq_mod_min` varchar(10) DEFAULT NULL,
  `ipaq_cam_dias` varchar(10) DEFAULT NULL,
  `ipaq_cam_min` varchar(10) DEFAULT NULL,
  `ipaq_sentado` varchar(10) DEFAULT NULL,
  `ipaq_met_min` int(11) DEFAULT NULL,
  `ipaq_categoria` enum('bajo','moderado','alto') DEFAULT NULL,
  `equipo` varchar(30) DEFAULT NULL,
  `objetivo_ejercicio` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuario` (`id_usuario`),
  KEY `idx_cuest_score` (`fitness_score`),
  KEY `idx_cuest_nivel` (`nivel_recomendado`),
  KEY `idx_cuest_fecha` (`fecha_respuesta`),
  CONSTRAINT `fk_cuest_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Respuestas del cuestionario de hábitos físicos';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cuestionario_habitos`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `cuestionario_habitos` WRITE;
/*!40000 ALTER TABLE `cuestionario_habitos` DISABLE KEYS */;
INSERT INTO `cuestionario_habitos` VALUES
(1,1,'mas_300','5_mas','fuerza','casa',NULL,70.0,170.0,24.2,'','salud','tiempo','regular','4_6','ninguna','ninguna','ninguno','','','bastante_equilibrada','','','','a_veces','indiferente','','bajo',80,'avanzado',NULL,'2026-10-01 22:39:27','2026-10-01 22:56:40',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),
(34,2,'menos_75','nunca','fuerza','casa',NULL,140.0,170.0,48.4,'','salud','tiempo','regular','4_6','ninguna','ninguna','ninguno','','','bastante_equilibrada','','','','a_veces','indiferente','','muy_bajo',30,'intermedio',NULL,'2026-10-13 21:37:13','2026-10-13 21:50:31',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),
(36,3,'mas_300','5_mas','fuerza','gimnasio',NULL,85.0,178.0,26.8,'','salud','tiempo','buena','4_6','ninguna','ninguna','ninguno','','','bastante_equilibrada','','','','a_veces','indiferente','','positivo',90,'avanzado',NULL,'2026-10-14 12:31:02','2026-10-14 12:31:02',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),
(37,4,'menos_75','nunca','fuerza','casa',NULL,60.0,1.6,NULL,'','salud','tiempo','buena','4_6','ninguna','ninguna','ninguno','','','bastante_equilibrada','','','','a_veces','indiferente','','variable',40,'intermedio',NULL,'2026-10-16 13:03:32','2026-10-16 13:03:32',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `cuestionario_habitos` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `cuestionarios_alimentacion`
--

DROP TABLE IF EXISTS `cuestionarios_alimentacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cuestionarios_alimentacion` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) unsigned NOT NULL,
  `objetivo` varchar(20) DEFAULT NULL COMMENT 'perder_peso|ganar_masa|mantener|mejorar_salud',
  `frecuencia_comidas` varchar(15) DEFAULT NULL COMMENT 'una_dos|tres|cuatro|cinco_mas',
  `desayuno` varchar(20) DEFAULT NULL COMMENT 'siempre|casi_siempre|a_veces|nunca',
  `verduras_frutas` varchar(15) DEFAULT NULL COMMENT 'diario|frecuente|ocasional|casi_nunca',
  `proteina` varchar(15) DEFAULT NULL COMMENT 'carnes|pescado|huevo_lacto|vegetal',
  `ultra_procesados` varchar(15) DEFAULT NULL COMMENT 'casi_nunca|una_vez|frecuente|diario',
  `agua` varchar(10) DEFAULT NULL COMMENT 'menos_4|4_6|6_8|mas_8',
  `alergias` varchar(15) DEFAULT NULL COMMENT 'ninguna|lactosa|gluten|otras',
  `presupuesto` varchar(20) DEFAULT NULL COMMENT 'muy_limitado|limitado|moderado|amplio',
  `cocina` varchar(15) DEFAULT NULL COMMENT 'completa|limitada|cafeteria|no',
  `actividad_fisica_alim` varchar(15) DEFAULT NULL COMMENT 'sedentario|leve|moderado|intenso',
  `horario` varchar(15) DEFAULT NULL COMMENT 'regular|irregular|nocturno|sin_tiempo',
  `nutricion_score` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT '0-100',
  `nivel_nutricion` varchar(15) NOT NULL DEFAULT 'mejorable' COMMENT 'deficiente|mejorable|adecuado|optimo',
  `plan_semanal` longtext DEFAULT NULL COMMENT 'Plan semanal generado por IA (JSON)',
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `verduras_dias` varchar(10) DEFAULT NULL,
  `frutas_dias` varchar(10) DEFAULT NULL,
  `lacteos_dias` varchar(10) DEFAULT NULL,
  `agua_vasos` varchar(10) DEFAULT NULL,
  `carnes_procesadas` varchar(10) DEFAULT NULL,
  `comida_rapida` varchar(10) DEFAULT NULL,
  `dulces` varchar(10) DEFAULT NULL,
  `pan_dulce` varchar(10) DEFAULT NULL,
  `botanas` varchar(10) DEFAULT NULL,
  `refrescos_dias` varchar(10) DEFAULT NULL,
  `refrescos_vasos` varchar(10) DEFAULT NULL,
  `desayuno_dias` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_alim_usuario` (`usuario_id`),
  KEY `idx_alim_score` (`nutricion_score`),
  KEY `idx_alim_nivel` (`nivel_nutricion`),
  KEY `idx_alim_objetivo` (`objetivo`),
  CONSTRAINT `fk_alim_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Perfil nutricional por usuario (cuestionario de 12 preguntas + dieta IA)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cuestionarios_alimentacion`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `cuestionarios_alimentacion` WRITE;
/*!40000 ALTER TABLE `cuestionarios_alimentacion` DISABLE KEYS */;
INSERT INTO `cuestionarios_alimentacion` VALUES
(1,1,'mejorar_salud','cuatro','casi_siempre','frecuente','huevo_lacto','frecuente','6_8','gluten','moderado','cafeteria','moderado','sin_tiempo',38,'adecuado','{\"dias\":[{\"dia\":\"Lunes\",\"comidas\":[{\"tiempo\":\"Desayuno\",\"descripcion\":\"Huevos revueltos con tortilla de maíz y frijoles\",\"kcal\":320},{\"tiempo\":\"Comida\",\"descripcion\":\"Sopa de nopales con pollo y jitomate\",\"kcal\":450},{\"tiempo\":\"Cena\",\"descripcion\":\"Yogur griego con fruta fresca\",\"kcal\":280}]},{\"dia\":\"Martes\",\"comidas\":[{\"tiempo\":\"Desayuno\",\"descripcion\":\"Tortilla de maíz con queso fresco y huevo\",\"kcal\":340},{\"tiempo\":\"Comida\",\"descripcion\":\"Caldo de res con calabaza y arroz rojo\",\"kcal\":520},{\"tiempo\":\"Cena\",\"descripcion\":\"Tostada de maíz con frijoles y aguacate\",\"kcal\":360}]},{\"dia\":\"Miércoles\",\"comidas\":[{\"tiempo\":\"Desayuno\",\"descripcion\":\"Huevos cocidos con leche y pan de maíz\",\"kcal\":420},{\"tiempo\":\"Comida\",\"descripcion\":\"Enchiladas suaves de pollo con frijoles\",\"kcal\":580},{\"tiempo\":\"Cena\",\"descripcion\":\"Batido de proteína con yogurt y plátano\",\"kcal\":340}]},{\"dia\":\"Jueves\",\"comidas\":[{\"tiempo\":\"Desayuno\",\"descripcion\":\"Tortilla de maíz, huevo y frijoles refritos\",\"kcal\":380},{\"tiempo\":\"Comida\",\"descripcion\":\"Mole rojo con pollo y arroz blanco\",\"kcal\":620},{\"tiempo\":\"Cena\",\"descripcion\":\"Huevos benedicta con tortilla de maíz\",\"kcal\":450}]},{\"dia\":\"Viernes\",\"comidas\":[{\"tiempo\":\"Desayuno\",\"descripcion\":\"Chilaquiles verdes con huevo y frijoles\",\"kcal\":480},{\"tiempo\":\"Comida\",\"descripcion\":\"Tacos de suadero con nopales y lechuga\",\"kcal\":560},{\"tiempo\":\"Cena\",\"descripcion\":\"Pozole blanco con huevo duro\",\"kcal\":420}]},{\"dia\":\"Sábado\",\"comidas\":[{\"tiempo\":\"Desayuno\",\"descripcion\":\"Huevos a la mexicana con tortilla de maíz\",\"kcal\":390},{\"tiempo\":\"Comida\",\"descripcion\":\"Mole negro con pavo y arroz rojo\",\"kcal\":650},{\"tiempo\":\"Cena\",\"descripcion\":\"Chiles en nogada con frijoles\",\"kcal\":480}]},{\"dia\":\"Domingo\",\"comidas\":[{\"tiempo\":\"Desayuno\",\"descripcion\":\"Molletes de maíz con frijoles y huevo\",\"kcal\":360},{\"tiempo\":\"Comida\",\"descripcion\":\"Pozole rojo con pollo y nopales\",\"kcal\":590},{\"tiempo\":\"Cena\",\"descripcion\":\"Arroz con leche y tlaya de huevo\",\"kcal\":340}]}],\"calorias_dia\":1800,\"nota\":\"Compra tortillas de maíz en lugar de harina. Usa frijoles como proteína principal para ahorrar. Bebe agua entre comidas.\"}','2026-10-01 22:47:09','2026-10-01 22:57:47',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),
(4,2,'mejorar_salud','cinco_mas','a_veces','ocasional','vegetal','frecuente','mas_8','lactosa','limitado','limitada','leve','nocturno',31,'mejorable','{\"dias\":[{\"dia\":\"Lunes\",\"comidas\":[{\"tiempo\":\"Desayuno\",\"descripcion\":\"Avena con plátano, chía y miel\",\"kcal\":320},{\"tiempo\":\"Comida\",\"descripcion\":\"Arroz rojo con frijoles y nopales\",\"kcal\":480},{\"tiempo\":\"Cena\",\"descripcion\":\"Tortilla de maíz con aguacate y jitomate\",\"kcal\":350}]},{\"dia\":\"Martes\",\"comidas\":[{\"tiempo\":\"Desayuno\",\"descripcion\":\"Batido de avena, plátano y semillas\",\"kcal\":280},{\"tiempo\":\"Comida\",\"descripcion\":\"Ensalada de chayote con frijoles negros\",\"kcal\":450},{\"tiempo\":\"Cena\",\"descripcion\":\"Tostadas de maíz con guacamole y jitomate\",\"kcal\":320}]},{\"dia\":\"Miércoles\",\"comidas\":[{\"tiempo\":\"Desayuno\",\"descripcion\":\"Gachas de avena con plátano y semillas\",\"kcal\":400},{\"tiempo\":\"Comida\",\"descripcion\":\"Arroz integral con lentejas y nopales\",\"kcal\":620},{\"tiempo\":\"Cena\",\"descripcion\":\"Tortillas de maíz con frijoles refritos\",\"kcal\":480}]},{\"dia\":\"Jueves\",\"comidas\":[{\"tiempo\":\"Desayuno\",\"descripcion\":\"Batido de plátano, avena y semillas\",\"kcal\":350},{\"tiempo\":\"Comida\",\"descripcion\":\"Arroz con garbanzos y jitomate asado\",\"kcal\":680},{\"tiempo\":\"Cena\",\"descripcion\":\"Tostadas de frijoles con aguacate y cebolla\",\"kcal\":520}]},{\"dia\":\"Viernes\",\"comidas\":[{\"tiempo\":\"Desayuno\",\"descripcion\":\"Avena con plátano, miel y chía\",\"kcal\":380},{\"tiempo\":\"Comida\",\"descripcion\":\"Arroz negro con frijoles y nopales\",\"kcal\":550},{\"tiempo\":\"Cena\",\"descripcion\":\"Tortillas de maíz con aguacate y jitomate\",\"kcal\":420}]},{\"dia\":\"Sábado\",\"comidas\":[{\"tiempo\":\"Desayuno\",\"descripcion\":\"Gachas de avena con plátano y semillas\",\"kcal\":450},{\"tiempo\":\"Comida\",\"descripcion\":\"Arroz con lentejas, nopales y jitomate\",\"kcal\":720},{\"tiempo\":\"Cena\",\"descripcion\":\"Tostadas de frijoles con aguacate y cebolla\",\"kcal\":580}]},{\"dia\":\"Domingo\",\"comidas\":[{\"tiempo\":\"Desayuno\",\"descripcion\":\"Batido de plátano, avena y semillas\",\"kcal\":420},{\"tiempo\":\"Comida\",\"descripcion\":\"Arroz integral con garbanzos y nopales\",\"kcal\":680},{\"tiempo\":\"Cena\",\"descripcion\":\"Tortillas de maíz con frijoles y aguacate\",\"kcal\":520}]}],\"calorias_dia\":1950,\"nota\":\"Este plan es vegano, sin lactosa y usa ingredientes mexicanos accesibles. Cocina en microondas con tortillas calientes, frijoles al vapor y arroz hervido.\"}','2026-10-13 21:37:13','2026-10-13 21:51:35',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),
(7,3,'ganar_masa','cuatro','nunca','ocasional','carnes','casi_nunca','mas_8','lactosa','amplio','completa','intenso','irregular',37,'adecuado','{\"dias\":[{\"dia\":\"Lunes\",\"comidas\":[{\"tiempo\":\"Desayuno\",\"descripcion\":\"Tortillas con frijoles, huevo y nopales\",\"kcal\":320},{\"tiempo\":\"Comida\",\"descripcion\":\"Arroz rojo con carne molida y jitomate\",\"kcal\":520},{\"tiempo\":\"Cena\",\"descripcion\":\"Tacos de suadero con lechuga y cebolla\",\"kcal\":400}]},{\"dia\":\"Martes\",\"comidas\":[{\"tiempo\":\"Desayuno\",\"descripcion\":\"Huevos revueltos con tortillas y frijoles\",\"kcal\":350},{\"tiempo\":\"Comida\",\"descripcion\":\"Caldo de res con verduras y arroz\",\"kcal\":480},{\"tiempo\":\"Cena\",\"descripcion\":\"Tostadas de atún con aguacate\",\"kcal\":360}]},{\"dia\":\"Miércoles\",\"comidas\":[{\"tiempo\":\"Desayuno\",\"descripcion\":\"Chilaquiles verdes con carne molida y huevo\",\"kcal\":420},{\"tiempo\":\"Comida\",\"descripcion\":\"Enchiladas suizas de pollo con arroz\",\"kcal\":650},{\"tiempo\":\"Cena\",\"descripcion\":\"Carne asada con nopales y frijoles\",\"kcal\":580}]},{\"dia\":\"Jueves\",\"comidas\":[{\"tiempo\":\"Desayuno\",\"descripcion\":\"Huevos cocidos con tortillas y aguacate\",\"kcal\":380},{\"tiempo\":\"Comida\",\"descripcion\":\"Mole de pavo con arroz y frijoles\",\"kcal\":620},{\"tiempo\":\"Cena\",\"descripcion\":\"Tacos al pastor con piña y lechuga\",\"kcal\":540}]},{\"dia\":\"Viernes\",\"comidas\":[{\"tiempo\":\"Desayuno\",\"descripcion\":\"Mango con yogurt griego, plátano y miel\",\"kcal\":380},{\"tiempo\":\"Comida\",\"descripcion\":\"Camarones en salsa verde con arroz\",\"kcal\":590},{\"tiempo\":\"Cena\",\"descripcion\":\"Pescado a la talla con guacamole\",\"kcal\":480}]},{\"dia\":\"Sábado\",\"comidas\":[{\"tiempo\":\"Desayuno\",\"descripcion\":\"Chilaquiles rojos con queso y huevo frito\",\"kcal\":520},{\"tiempo\":\"Comida\",\"descripcion\":\"Mole poblano de pavo con arroz blanco\",\"kcal\":680},{\"tiempo\":\"Cena\",\"descripcion\":\"Tacos dorados de carne molida\",\"kcal\":520}]},{\"dia\":\"Domingo\",\"comidas\":[{\"tiempo\":\"Desayuno\",\"descripcion\":\"Chorizo con huevo, tortillas y frijoles\",\"kcal\":480},{\"tiempo\":\"Comida\",\"descripcion\":\"Mole de olla con pollo y arroz\",\"kcal\":720},{\"tiempo\":\"Cena\",\"descripcion\":\"Carne asada con nopales y tortillas\",\"kcal\":560}]}],\"calorias_dia\":1800,\"nota\":\"Aumenta las porciones de proteína en días de entrenamiento intenso. Mantén hidratación constante.\"}','2026-10-14 12:31:02','2026-10-14 12:32:29',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),
(9,4,'mantener','tres','siempre','frecuente','carnes','una_vez','4_6','ninguna','limitado','completa','leve','regular',44,'adecuado','{\"dias\":[{\"dia\":\"Lunes\",\"comidas\":[{\"tiempo\":\"Desayuno\",\"descripcion\":\"Tortillas con frijoles y huevo revuelto\",\"kcal\":320},{\"tiempo\":\"Comida\",\"descripcion\":\"Arroz blanco con nopales y pollo a la plancha\",\"kcal\":480},{\"tiempo\":\"Cena\",\"descripcion\":\"Ensalada de chayote con atún en tortilla\",\"kcal\":350}]},{\"dia\":\"Martes\",\"comidas\":[{\"tiempo\":\"Desayuno\",\"descripcion\":\"Tostadas con frijoles y jitomate picado\",\"kcal\":290},{\"tiempo\":\"Comida\",\"descripcion\":\"Caldo de pollo con verduras y arroz\",\"kcal\":460},{\"tiempo\":\"Cena\",\"descripcion\":\"Huevos cocidos con aguacate y tortilla\",\"kcal\":380}]},{\"dia\":\"Miércoles\",\"comidas\":[{\"tiempo\":\"Desayuno\",\"descripcion\":\"Tortas de huevo con frijoles y jitomate\",\"kcal\":420},{\"tiempo\":\"Comida\",\"descripcion\":\"Carnitas caseras con arroz y nopales\",\"kcal\":650},{\"tiempo\":\"Cena\",\"descripcion\":\"Pollo asado con ensalada de jitomate\",\"kcal\":480}]},{\"dia\":\"Jueves\",\"comidas\":[{\"tiempo\":\"Desayuno\",\"descripcion\":\"Huevos fritos con frijoles y tortilla\",\"kcal\":380},{\"tiempo\":\"Comida\",\"descripcion\":\"Chuletas de cerdo al horno con arroz\",\"kcal\":620},{\"tiempo\":\"Cena\",\"descripcion\":\"Pechuga de pollo a la plancha con ensalada\",\"kcal\":450}]},{\"dia\":\"Viernes\",\"comidas\":[{\"tiempo\":\"Desayuno\",\"descripcion\":\"Avena con plátano, miel y nueces\",\"kcal\":360},{\"tiempo\":\"Comida\",\"descripcion\":\"Tacos de suadero caseros con frijoles\",\"kcal\":580},{\"tiempo\":\"Cena\",\"descripcion\":\"Quesadillas de flor de calabaza con pollo\",\"kcal\":420}]},{\"dia\":\"Sábado\",\"comidas\":[{\"tiempo\":\"Desayuno\",\"descripcion\":\"Molletes con frijoles y huevo frito\",\"kcal\":410},{\"tiempo\":\"Comida\",\"descripcion\":\"Enchiladas verdes con pollo y arroz\",\"kcal\":680},{\"tiempo\":\"Cena\",\"descripcion\":\"Sopes de carne molida con frijoles\",\"kcal\":520}]},{\"dia\":\"Domingo\",\"comidas\":[{\"tiempo\":\"Desayuno\",\"descripcion\":\"Chilaquiles verdes con huevo y frijoles\",\"kcal\":440},{\"tiempo\":\"Comida\",\"descripcion\":\"Mole de pollo con arroz y nopales\",\"kcal\":720},{\"tiempo\":\"Cena\",\"descripcion\":\"Sopa de fideo con carne molida\",\"kcal\":480}]}],\"calorias_dia\":1850,\"nota\":\"Aprovecha ofertas en pollo y cerdo para mantener el presupuesto. Compra tortillas y frijoles al por mayor.\"}','2026-10-16 13:03:33','2026-10-16 13:05:10',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `cuestionarios_alimentacion` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `cuestionarios_clinicos`
--

DROP TABLE IF EXISTS `cuestionarios_clinicos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cuestionarios_clinicos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) unsigned NOT NULL,
  `visitas_medico` varchar(20) DEFAULT NULL COMMENT 'nunca|rara_vez|anual|frecuente',
  `condicion_cronica` varchar(20) DEFAULT NULL COMMENT 'ninguna|controlada|sin_control|prefiero_no',
  `medicamentos` varchar(20) DEFAULT NULL COMMENT 'ninguno|vitaminas|prescripcion|varios',
  `presion_arterial` varchar(10) DEFAULT NULL COMMENT 'normal|alta|baja|no_se',
  `sueno` varchar(10) DEFAULT NULL COMMENT 'menos_5|5_6|7_8|mas_9',
  `tabaco` varchar(15) DEFAULT NULL COMMENT 'nunca|exfumador|ocasional|regular',
  `alcohol` varchar(15) DEFAULT NULL COMMENT 'nunca|social|frecuente|diario',
  `sintomas_frecuentes` varchar(15) DEFAULT NULL COMMENT 'ninguno|fatiga|dolor|digestivo',
  `vacunas` varchar(15) DEFAULT NULL COMMENT 'completo|incompleto|no_se|no',
  `estres_fisico` varchar(15) DEFAULT NULL COMMENT 'excelente|bueno|regular|malo',
  `hidratacion` varchar(10) DEFAULT NULL COMMENT 'menos_4|4_6|6_8|mas_8',
  `urgencias` varchar(15) DEFAULT NULL COMMENT 'no|una|dos_tres|mas_tres',
  `salud_score` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT '0-100',
  `nivel_salud` varchar(15) NOT NULL DEFAULT 'moderado' COMMENT 'critico|moderado|bueno|optimo',
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `srh_salud` varchar(12) DEFAULT NULL,
  `tabaco_actual` varchar(12) DEFAULT NULL,
  `vapeo_actual` varchar(12) DEFAULT NULL,
  `audit_frecuencia` varchar(15) DEFAULT NULL,
  `audit_cantidad` varchar(10) DEFAULT NULL,
  `audit_atracon` varchar(15) DEFAULT NULL,
  `audit_c_total` tinyint(4) DEFAULT NULL,
  `presion_dx` varchar(10) DEFAULT NULL,
  `glucosa_dx` varchar(10) DEFAULT NULL,
  `colesterol_dx` varchar(10) DEFAULT NULL,
  `antecedente_diabetes` varchar(15) DEFAULT NULL,
  `sueno_horas` varchar(10) DEFAULT NULL,
  `chequeo_ultimo` varchar(15) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_clinico_usuario` (`usuario_id`),
  KEY `idx_clinico_score` (`salud_score`),
  KEY `idx_clinico_nivel` (`nivel_salud`),
  CONSTRAINT `fk_clinico_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Perfil de salud clínica por usuario (cuestionario de 12 preguntas)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cuestionarios_clinicos`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `cuestionarios_clinicos` WRITE;
/*!40000 ALTER TABLE `cuestionarios_clinicos` DISABLE KEYS */;
INSERT INTO `cuestionarios_clinicos` VALUES
(7,1,'frecuente','prefiero_no','varios','baja','7_8','ocasional','frecuente','dolor','no_se','regular','6_8','dos_tres',49,'optimo','2026-10-01 22:41:39','2026-10-01 22:56:40',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),
(34,2,'frecuente','sin_control','prescripcion','alta','7_8','ocasional','frecuente','dolor','no','malo','mas_8','mas_tres',49,'optimo','2026-10-13 21:37:13','2026-10-13 21:50:31',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),
(36,3,'nunca','ninguna','ninguno','normal','5_6','nunca','social','ninguno','completo','excelente','mas_8','no',30,'bueno','2026-10-14 12:31:02','2026-10-14 12:31:02',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),
(37,4,'anual','controlada','prescripcion','no_se','7_8','nunca','nunca','digestivo','completo','bueno','4_6','no',65,'optimo','2026-10-16 13:03:33','2026-10-16 13:03:33',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `cuestionarios_clinicos` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `cuestionarios_psicologicos`
--

DROP TABLE IF EXISTS `cuestionarios_psicologicos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cuestionarios_psicologicos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) unsigned NOT NULL,
  `estres_general` varchar(15) DEFAULT NULL COMMENT 'nunca|ocasional|frecuente|constante',
  `ansiedad` varchar(15) DEFAULT NULL COMMENT 'nunca|leve|moderado|intenso',
  `estado_animo` varchar(15) DEFAULT NULL COMMENT 'positivo|variable|bajo|muy_bajo',
  `sueno_psico` varchar(20) DEFAULT NULL COMMENT 'no|ocasional|frecuente|casi_siempre',
  `relaciones` varchar(15) DEFAULT NULL COMMENT 'muy_buenas|buenas|tensas|aislado',
  `rendimiento_academico` varchar(15) DEFAULT NULL COMMENT 'no|poco|moderado|mucho',
  `autocuidado` varchar(15) DEFAULT NULL COMMENT 'siempre|a_veces|pocas|nunca',
  `autoestima` varchar(15) DEFAULT NULL COMMENT 'alta|moderada|baja|muy_baja',
  `apoyo_profesional` varchar(15) DEFAULT NULL COMMENT 'si_activo|si_pasado|no_quiero|no',
  `consumo_sustancias` varchar(15) DEFAULT NULL COMMENT 'no|cafeina|alcohol|varios',
  `pensamientos_neg` varchar(15) DEFAULT NULL COMMENT 'raramente|ocasional|frecuente|constante',
  `metas_bienestar` varchar(15) DEFAULT NULL COMMENT 'estres|ansiedad|autoestima|relaciones',
  `psico_score` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT '0-100',
  `nivel_psicologico` varchar(15) NOT NULL DEFAULT 'moderado' COMMENT 'critico|vulnerable|moderado|resiliente',
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `phq_interes` tinyint(4) DEFAULT NULL,
  `phq_animo` tinyint(4) DEFAULT NULL,
  `phq_sueno` tinyint(4) DEFAULT NULL,
  `phq_energia` tinyint(4) DEFAULT NULL,
  `phq_apetito` tinyint(4) DEFAULT NULL,
  `phq_autoestima` tinyint(4) DEFAULT NULL,
  `phq_concentracion` tinyint(4) DEFAULT NULL,
  `phq_movimiento` tinyint(4) DEFAULT NULL,
  `gad_nervios` tinyint(4) DEFAULT NULL,
  `gad_control` tinyint(4) DEFAULT NULL,
  `gad_preocupacion` tinyint(4) DEFAULT NULL,
  `gad_relajarse` tinyint(4) DEFAULT NULL,
  `gad_inquietud` tinyint(4) DEFAULT NULL,
  `gad_irritable` tinyint(4) DEFAULT NULL,
  `gad_miedo` tinyint(4) DEFAULT NULL,
  `phq8_total` tinyint(4) DEFAULT NULL,
  `gad7_total` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_psico_usuario` (`usuario_id`),
  KEY `idx_psico_score` (`psico_score`),
  KEY `idx_psico_nivel` (`nivel_psicologico`),
  CONSTRAINT `fk_psico_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Perfil de bienestar psicológico por usuario (cuestionario de 12 preguntas)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cuestionarios_psicologicos`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `cuestionarios_psicologicos` WRITE;
/*!40000 ALTER TABLE `cuestionarios_psicologicos` DISABLE KEYS */;
INSERT INTO `cuestionarios_psicologicos` VALUES
(1,1,'ocasional','leve','bajo','ocasional','tensas','moderado','pocas','baja','no_quiero','alcohol','frecuente','autoestima',33,'vulnerable','2026-10-01 22:47:09','2026-10-01 22:56:40',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),
(3,2,'frecuente','moderado','muy_bajo','frecuente','buenas','poco','pocas','baja','no_quiero','varios','frecuente','autoestima',26,'critico','2026-10-13 21:37:13','2026-10-13 21:50:31',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),
(5,3,'ocasional','leve','positivo','no','muy_buenas','no','nunca','alta','si_pasado','no','raramente','estres',68,'resiliente','2026-10-14 12:31:02','2026-10-14 12:31:02',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),
(6,4,'ocasional','leve','variable','ocasional','buenas','poco','pocas','moderada','si_pasado','no','raramente','ansiedad',51,'moderado','2026-10-16 13:03:33','2026-10-16 13:03:33',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `cuestionarios_psicologicos` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `ejercicio`
--

DROP TABLE IF EXISTS `ejercicio`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ejercicio` (
  `id_ejercicio` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `grupo_muscular` varchar(60) NOT NULL COMMENT 'pecho, espalda, pierna, hombro, brazo, core, gluteo',
  `nivel_dificultad` enum('principiante','intermedio','avanzado') NOT NULL DEFAULT 'principiante',
  `equipo_necesario` varchar(120) DEFAULT NULL,
  `imagen_url` varchar(500) DEFAULT NULL,
  `musculo_antagonista` varchar(50) DEFAULT NULL COMMENT 'Grupo muscular antagonista principal',
  `video_url` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_ejercicio`),
  KEY `idx_grupo` (`grupo_muscular`),
  KEY `idx_nivel` (`nivel_dificultad`)
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ejercicio`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `ejercicio` WRITE;
/*!40000 ALTER TABLE `ejercicio` DISABLE KEYS */;
INSERT INTO `ejercicio` VALUES
(1,'Press Banca',NULL,'Pecho','intermedio','Barra y banco',NULL,'espalda',NULL,'2026-03-15 22:03:07'),
(2,'Press Inclinado',NULL,'Pecho','intermedio','Barra y banco inclinado',NULL,'espalda',NULL,'2026-03-15 22:03:07'),
(3,'Press Declinado',NULL,'Pecho','intermedio','Barra y banco declinado',NULL,'espalda',NULL,'2026-03-15 22:03:07'),
(4,'Aperturas con Mancuernas',NULL,'Pecho','principiante','Mancuernas y banco',NULL,'espalda',NULL,'2026-03-15 22:03:07'),
(5,'Fondos en paralelas',NULL,'Pecho','intermedio','Paralelas',NULL,'espalda',NULL,'2026-03-15 22:03:07'),
(6,'Press con Mancuernas',NULL,'Pecho','principiante','Mancuernas y banco',NULL,'espalda',NULL,'2026-03-15 22:03:07'),
(7,'Crossover en polea',NULL,'Pecho','principiante','Poleas',NULL,'espalda',NULL,'2026-03-15 22:03:07'),
(8,'Dominadas',NULL,'Espalda','intermedio','Barra de dominadas',NULL,'pecho',NULL,'2026-03-15 22:03:07'),
(9,'Remo con Barra',NULL,'Espalda','intermedio','Barra',NULL,'pecho',NULL,'2026-03-15 22:03:07'),
(10,'Remo con Mancuerna',NULL,'Espalda','principiante','Mancuerna y banco',NULL,'pecho',NULL,'2026-03-15 22:03:07'),
(11,'Jalón al Pecho',NULL,'Espalda','principiante','Polea alta',NULL,'pecho',NULL,'2026-03-15 22:03:07'),
(12,'Remo en Polea Baja',NULL,'Espalda','principiante','Polea baja',NULL,'pecho',NULL,'2026-03-15 22:03:07'),
(13,'Peso Muerto',NULL,'Espalda','avanzado','Barra',NULL,'pecho',NULL,'2026-03-15 22:03:07'),
(14,'Hiperextensiones',NULL,'Espalda','principiante','Banco romano',NULL,'pecho',NULL,'2026-03-15 22:03:07'),
(15,'Press Militar',NULL,'Hombros','intermedio','Barra',NULL,NULL,NULL,'2026-03-15 22:03:07'),
(16,'Press Arnold',NULL,'Hombros','intermedio','Mancuernas',NULL,NULL,NULL,'2026-03-15 22:03:07'),
(17,'Elevaciones Laterales',NULL,'Hombros','principiante','Mancuernas',NULL,NULL,NULL,'2026-03-15 22:03:07'),
(18,'Elevaciones Frontales',NULL,'Hombros','principiante','Mancuernas',NULL,NULL,NULL,'2026-03-15 22:03:07'),
(19,'Pájaros',NULL,'Hombros','principiante','Mancuernas',NULL,NULL,NULL,'2026-03-15 22:03:07'),
(20,'Curl con Barra',NULL,'Bíceps','principiante','Barra',NULL,'tríceps',NULL,'2026-03-15 22:03:07'),
(21,'Curl con Mancuernas',NULL,'Bíceps','principiante','Mancuernas',NULL,'tríceps',NULL,'2026-03-15 22:03:07'),
(22,'Curl Martillo',NULL,'Bíceps','principiante','Mancuernas',NULL,'tríceps',NULL,'2026-03-15 22:03:07'),
(23,'Curl en Polea Baja',NULL,'Bíceps','principiante','Polea baja',NULL,'tríceps',NULL,'2026-03-15 22:03:07'),
(24,'Curl Concentrado',NULL,'Bíceps','principiante','Mancuerna',NULL,'tríceps',NULL,'2026-03-15 22:03:07'),
(25,'Press Francés',NULL,'Tríceps','intermedio','Barra EZ',NULL,'bíceps',NULL,'2026-03-15 22:03:07'),
(26,'Extensión en Polea Alta',NULL,'Tríceps','principiante','Polea alta',NULL,'bíceps',NULL,'2026-03-15 22:03:07'),
(27,'Patada de Tríceps',NULL,'Tríceps','principiante','Mancuerna',NULL,'bíceps',NULL,'2026-03-15 22:03:07'),
(28,'Fondos en Banco',NULL,'Tríceps','principiante','Banco',NULL,'bíceps',NULL,'2026-03-15 22:03:07'),
(29,'Sentadilla',NULL,'Piernas','intermedio','Barra',NULL,NULL,NULL,'2026-03-15 22:03:07'),
(30,'Sentadilla Frontal',NULL,'Piernas','avanzado','Barra',NULL,NULL,NULL,'2026-03-15 22:03:07'),
(31,'Prensa de Piernas',NULL,'Piernas','principiante','Máquina prensa',NULL,NULL,NULL,'2026-03-15 22:03:07'),
(32,'Extensión de Cuádriceps',NULL,'Piernas','principiante','Máquina extensora',NULL,NULL,NULL,'2026-03-15 22:03:07'),
(33,'Curl de Isquiotibiales',NULL,'Piernas','principiante','Máquina curl',NULL,NULL,NULL,'2026-03-15 22:03:07'),
(34,'Peso Muerto Rumano',NULL,'Piernas','intermedio','Barra',NULL,NULL,NULL,'2026-03-15 22:03:07'),
(35,'Zancadas',NULL,'Piernas','principiante','Mancuernas',NULL,NULL,NULL,'2026-03-15 22:03:07'),
(36,'Elevación de Talones',NULL,'Piernas','principiante','Máquina o mancuernas',NULL,NULL,NULL,'2026-03-15 22:03:07'),
(37,'Hip Thrust',NULL,'Piernas','intermedio','Barra y banco',NULL,NULL,NULL,'2026-03-15 22:03:07'),
(38,'Crunch',NULL,'Abdomen','principiante','Colchoneta',NULL,NULL,NULL,'2026-03-15 22:03:07'),
(39,'Plancha',NULL,'Abdomen','principiante','Colchoneta',NULL,NULL,NULL,'2026-03-15 22:03:07'),
(40,'Elevación de Piernas',NULL,'Abdomen','intermedio','Barra de dominadas',NULL,NULL,NULL,'2026-03-15 22:03:07'),
(41,'Rueda Abdominal',NULL,'Abdomen','avanzado','Rueda abdominal',NULL,NULL,NULL,'2026-03-15 22:03:07'),
(42,'Crunch en Polea',NULL,'Abdomen','principiante','Polea alta',NULL,NULL,NULL,'2026-03-15 22:03:07'),
(43,'Carrera en Caminadora',NULL,'Cardio','principiante','Caminadora',NULL,NULL,NULL,'2026-03-15 22:03:07'),
(44,'Bicicleta Estática',NULL,'Cardio','principiante','Bicicleta estática',NULL,NULL,NULL,'2026-03-15 22:03:07'),
(45,'Cuerda para Saltar',NULL,'Cardio','principiante','Cuerda',NULL,NULL,NULL,'2026-03-15 22:03:07'),
(46,'Remo Ergómetro',NULL,'Cardio','intermedio','Máquina de remo',NULL,NULL,NULL,'2026-03-15 22:03:07'),
(47,'Press de banca','Empuje horizontal con barra','pecho','intermedio','gimnasio_completo',NULL,'espalda',NULL,'2026-05-23 15:11:26'),
(48,'Sentadilla','ejercicio compuesto de piernas','piernas','principiante','gimnasio_completo',NULL,NULL,NULL,'2026-05-23 15:11:26'),
(49,'Peso muerto','Tracción del suelo con barra','espalda','avanzado','gimnasio_completo',NULL,'pecho',NULL,'2026-05-23 15:11:26'),
(50,'Curl de bíceps','Flexión de codo con mancuerna','biceps','principiante','mancuernas',NULL,'tríceps',NULL,'2026-05-23 15:11:26'),
(51,'Press militar','Empuje vertical sobre cabeza','hombros','intermedio','mancuernas',NULL,NULL,NULL,'2026-05-23 15:11:26'),
(52,'Dominadas','Jalón con peso corporal','espalda','intermedio','peso_corporal',NULL,'pecho',NULL,'2026-05-23 15:11:26'),
(53,'Flexiones','Empuje horizontal con peso corporal','pecho','principiante','ninguno',NULL,'espalda',NULL,'2026-05-23 15:11:26'),
(54,'Plancha','Isométrico de core','core','principiante','ninguno',NULL,NULL,NULL,'2026-05-23 15:11:26'),
(55,'Hip thrust','Extensión de cadera con barra','gluteos','intermedio','gimnasio_completo',NULL,NULL,NULL,'2026-05-23 15:11:26'),
(56,'Face pull','Tracción hacia la cara con polea','hombros','principiante','gimnasio_completo',NULL,NULL,NULL,'2026-05-23 15:11:26'),
(57,'Curl martillo','Flexión neutral de codo','biceps','principiante','mancuernas',NULL,'tríceps',NULL,'2026-05-23 15:11:26'),
(58,'Extensión tríceps','Extensión sobre cabeza','triceps','principiante','mancuernas',NULL,'bíceps',NULL,'2026-05-23 15:11:26'),
(59,'Remo con mancuerna','Tracción unilateral','espalda','principiante','mancuernas',NULL,'pecho',NULL,'2026-05-23 15:11:26'),
(60,'Zancadas','Estocadas con mancuernas','piernas','principiante','mancuernas',NULL,NULL,NULL,'2026-05-23 15:11:26'),
(61,'Elevaciones laterales','Abducción de hombro','hombros','principiante','mancuernas',NULL,NULL,NULL,'2026-05-23 15:11:26');
/*!40000 ALTER TABLE `ejercicio` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `favorito`
--

DROP TABLE IF EXISTS `favorito`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `favorito` (
  `id_favorito` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `id_ejercicio` int(10) unsigned NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_favorito`),
  UNIQUE KEY `uq_fav` (`id_usuario`,`id_ejercicio`),
  KEY `id_ejercicio` (`id_ejercicio`),
  CONSTRAINT `favorito_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE,
  CONSTRAINT `favorito_ibfk_2` FOREIGN KEY (`id_ejercicio`) REFERENCES `ejercicio` (`id_ejercicio`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `favorito`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `favorito` WRITE;
/*!40000 ALTER TABLE `favorito` DISABLE KEYS */;
/*!40000 ALTER TABLE `favorito` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `ia_conversacion`
--

DROP TABLE IF EXISTS `ia_conversacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ia_conversacion` (
  `id_mensaje` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `modulo` enum('ejercicio','psicologia','alimentacion','clinico') NOT NULL,
  `rol` enum('user','assistant') NOT NULL,
  `contenido` text NOT NULL,
  `fue_alerta` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 si activó protocolo de crisis/urgencia',
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_mensaje`),
  KEY `idx_usuario_modulo` (`id_usuario`,`modulo`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `fk_iac_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ia_conversacion`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `ia_conversacion` WRITE;
/*!40000 ALTER TABLE `ia_conversacion` DISABLE KEYS */;
/*!40000 ALTER TABLE `ia_conversacion` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `logro`
--

DROP TABLE IF EXISTS `logro`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `logro` (
  `id_logro` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `criterio` varchar(255) DEFAULT NULL COMMENT 'Descripción legible del criterio',
  `icono` varchar(100) DEFAULT NULL,
  `xp_recompensa` smallint(5) unsigned DEFAULT 100,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_logro`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `logro`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `logro` WRITE;
/*!40000 ALTER TABLE `logro` DISABLE KEYS */;
INSERT INTO `logro` VALUES
(1,'Primera Conexión','Completa tu primera sesión','1 sesión registrada','icon_bolt',100,'2026-05-23 13:34:30'),
(2,'Constancia Nivel 1','Entrena 7 días consecutivos','Racha de 7 días','icon_fire',250,'2026-05-23 13:34:30'),
(3,'Constancia Nivel 2','Entrena 30 días consecutivos','Racha de 30 días','icon_crown',500,'2026-05-23 13:34:30'),
(4,'Tonelaje Básico','Mueve 1,000 kg en total','Volumen acumulado ≥ 1,000 kg','icon_weight',150,'2026-05-23 13:34:30'),
(5,'Mega Tonelaje','Mueve 10,000 kg en total','Volumen acumulado ≥ 10,000 kg','icon_rocket',400,'2026-05-23 13:34:30'),
(6,'Explorador','Registra 10 ejercicios distintos','10 ejercicios únicos','icon_map',200,'2026-05-23 13:34:30'),
(7,'Maratonista','Acumula 1,000 minutos de ejercicio','1,000 minutos totales','icon_clock',300,'2026-05-23 13:34:30'),
(8,'Élite','Alcanza nivel Avanzado en el perfil','nivel = avanzado','icon_star',500,'2026-05-23 13:34:30');
/*!40000 ALTER TABLE `logro` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `mision`
--

DROP TABLE IF EXISTS `mision`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mision` (
  `id_mision` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `tipo` enum('diaria','semanal') NOT NULL DEFAULT 'diaria',
  `meta_cantidad` smallint(5) unsigned NOT NULL DEFAULT 1,
  `xp_recompensa` smallint(5) unsigned DEFAULT 50,
  PRIMARY KEY (`id_mision`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mision`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `mision` WRITE;
/*!40000 ALTER TABLE `mision` DISABLE KEYS */;
INSERT INTO `mision` VALUES
(1,'Entrenamiento diario','Completa 1 sesión hoy','diaria',1,50),
(2,'Hidratación activa','Registra 3 series de cualquier ejercicio','diaria',3,30),
(3,'Semana completa','Entrena 4 veces esta semana','semanal',4,150),
(4,'Volumen semanal','Mueve 500 kg esta semana','semanal',500,200),
(5,'Variedad muscular','Trabaja 3 grupos musculares distintos esta semana','semanal',3,120);
/*!40000 ALTER TABLE `mision` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `nutri_cita`
--

DROP TABLE IF EXISTS `nutri_cita`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nutri_cita` (
  `id_cita` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `motivo` enum('control_peso','alimentacion_deportiva','condicion_medica','vegetariano_vegano','presupuesto','otro') NOT NULL DEFAULT 'control_peso',
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `estado` enum('pendiente','confirmada','cancelada','atendida') NOT NULL DEFAULT 'pendiente',
  `notas_usuario` text DEFAULT NULL,
  `notas_nutriologo` text DEFAULT NULL,
  `folio` varchar(12) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_cita`),
  UNIQUE KEY `uq_folio` (`folio`),
  KEY `idx_usuario` (`id_usuario`),
  CONSTRAINT `fk_nc_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `nutri_cita`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `nutri_cita` WRITE;
/*!40000 ALTER TABLE `nutri_cita` DISABLE KEYS */;
/*!40000 ALTER TABLE `nutri_cita` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `nutri_plan`
--

DROP TABLE IF EXISTS `nutri_plan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nutri_plan` (
  `id_plan` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `objetivo` enum('bajar_peso','subir_peso','mantener','rendimiento','salud_general') NOT NULL,
  `calorias_objetivo` smallint(5) unsigned DEFAULT NULL,
  `duracion_semanas` tinyint(3) unsigned NOT NULL DEFAULT 4,
  `contenido` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Plan completo generado' CHECK (json_valid(`contenido`)),
  `generado_ia` tinyint(1) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_plan`),
  KEY `idx_usuario` (`id_usuario`),
  CONSTRAINT `fk_np_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `nutri_plan`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `nutri_plan` WRITE;
/*!40000 ALTER TABLE `nutri_plan` DISABLE KEYS */;
/*!40000 ALTER TABLE `nutri_plan` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `nutri_registro_diario`
--

DROP TABLE IF EXISTS `nutri_registro_diario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nutri_registro_diario` (
  `id_registro` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `fecha` date NOT NULL,
  `desayuno` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`desayuno`)),
  `comida` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`comida`)),
  `cena` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`cena`)),
  `snacks` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`snacks`)),
  `agua_vasos` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `calorias_estimadas` smallint(5) unsigned DEFAULT NULL,
  `proteina_g` decimal(6,1) DEFAULT NULL,
  `carbohidratos_g` decimal(6,1) DEFAULT NULL,
  `grasa_g` decimal(6,1) DEFAULT NULL,
  `imc` decimal(4,1) DEFAULT NULL,
  `peso_kg` decimal(5,2) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_registro`),
  UNIQUE KEY `uq_usuario_fecha` (`id_usuario`,`fecha`),
  KEY `idx_fecha_peso` (`id_usuario`,`fecha`,`peso_kg`),
  CONSTRAINT `fk_nrd_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `nutri_registro_diario`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `nutri_registro_diario` WRITE;
/*!40000 ALTER TABLE `nutri_registro_diario` DISABLE KEYS */;
/*!40000 ALTER TABLE `nutri_registro_diario` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `progreso_usuario`
--

DROP TABLE IF EXISTS `progreso_usuario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `progreso_usuario` (
  `id_progreso` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `fecha` date NOT NULL,
  `peso_corporal_kg` decimal(5,2) DEFAULT NULL,
  `volumen_total_kg` decimal(10,2) DEFAULT 0.00,
  `sesiones_completadas` smallint(5) unsigned DEFAULT 0,
  `calorias_quemadas` smallint(5) unsigned DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_progreso`),
  UNIQUE KEY `uq_usuario_fecha` (`id_usuario`,`fecha`),
  KEY `idx_usuario_fecha` (`id_usuario`,`fecha`),
  KEY `idx_pu_usuario_fecha` (`id_usuario`,`fecha`),
  CONSTRAINT `fk_progreso_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `progreso_usuario`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `progreso_usuario` WRITE;
/*!40000 ALTER TABLE `progreso_usuario` DISABLE KEYS */;
/*!40000 ALTER TABLE `progreso_usuario` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `psico_cita`
--

DROP TABLE IF EXISTS `psico_cita`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `psico_cita` (
  `id_cita` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `tipo_apoyo` enum('estres_academico','ansiedad','depresion','relaciones','crisis','duelo','identidad','otro') NOT NULL DEFAULT 'estres_academico',
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `modalidad` enum('presencial','videollamada') NOT NULL DEFAULT 'presencial',
  `estado` enum('pendiente','confirmada','cancelada','atendida') NOT NULL DEFAULT 'pendiente',
  `notas_usuario` text DEFAULT NULL,
  `notas_profesional` text DEFAULT NULL,
  `folio` varchar(12) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_cita`),
  UNIQUE KEY `uq_folio` (`folio`),
  KEY `idx_usuario` (`id_usuario`),
  KEY `idx_fecha` (`fecha`),
  CONSTRAINT `fk_pc_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `psico_cita`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `psico_cita` WRITE;
/*!40000 ALTER TABLE `psico_cita` DISABLE KEYS */;
/*!40000 ALTER TABLE `psico_cita` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `psico_registro_animo`
--

DROP TABLE IF EXISTS `psico_registro_animo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `psico_registro_animo` (
  `id_registro` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `estado_animo` enum('muy_mal','mal','regular','bien','muy_bien') NOT NULL,
  `emoji` varchar(10) DEFAULT NULL,
  `factores` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Array de factores seleccionados' CHECK (json_valid(`factores`)),
  `nota_libre` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_registro`),
  KEY `idx_usuario_fecha` (`id_usuario`,`fecha`),
  KEY `idx_usuario_fecha_hora` (`id_usuario`,`fecha`,`hora`),
  CONSTRAINT `fk_pra_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `psico_registro_animo`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `psico_registro_animo` WRITE;
/*!40000 ALTER TABLE `psico_registro_animo` DISABLE KEYS */;
/*!40000 ALTER TABLE `psico_registro_animo` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `psico_test_estres`
--

DROP TABLE IF EXISTS `psico_test_estres`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `psico_test_estres` (
  `id_test` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `tipo_test` varchar(20) NOT NULL DEFAULT 'PSS10',
  `respuestas` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Array de 10 valores 0-4' CHECK (json_valid(`respuestas`)),
  `puntaje_total` tinyint(3) unsigned NOT NULL,
  `nivel` enum('bajo','moderado','alto','minimo','leve','moderado_severo','severo') NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_test`),
  KEY `idx_usuario` (`id_usuario`),
  KEY `idx_usuario_tipo` (`id_usuario`,`tipo_test`),
  CONSTRAINT `fk_pte_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `psico_test_estres`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `psico_test_estres` WRITE;
/*!40000 ALTER TABLE `psico_test_estres` DISABLE KEYS */;
/*!40000 ALTER TABLE `psico_test_estres` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `registro_entrenamiento`
--

DROP TABLE IF EXISTS `registro_entrenamiento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `registro_entrenamiento` (
  `id_registro` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_sesion` int(10) unsigned NOT NULL,
  `id_usuario` int(10) unsigned NOT NULL,
  `id_ejercicio` int(10) unsigned NOT NULL,
  `id_rutina` int(10) unsigned DEFAULT NULL,
  `numero_serie` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `repeticiones` tinyint(3) unsigned DEFAULT NULL,
  `peso_kg` decimal(6,2) DEFAULT 0.00,
  `duracion_minutos` int(10) unsigned DEFAULT NULL,
  `notas` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `volumen_total_kg` decimal(10,2) NOT NULL DEFAULT 0.00,
  `sensacion` enum('excelente','buena','regular','mala') DEFAULT NULL,
  PRIMARY KEY (`id_registro`),
  KEY `idx_sesion` (`id_sesion`),
  KEY `idx_usuario` (`id_usuario`),
  KEY `idx_ejercicio` (`id_ejercicio`),
  KEY `fk_reg_rutina` (`id_rutina`),
  KEY `idx_re_usuario_fecha` (`id_usuario`,`created_at`),
  CONSTRAINT `fk_reg_ejercicio` FOREIGN KEY (`id_ejercicio`) REFERENCES `ejercicio` (`id_ejercicio`) ON UPDATE CASCADE,
  CONSTRAINT `fk_reg_rutina` FOREIGN KEY (`id_rutina`) REFERENCES `rutina` (`id_rutina`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_reg_sesion` FOREIGN KEY (`id_sesion`) REFERENCES `sesion` (`id_sesion`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_reg_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `registro_entrenamiento`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `registro_entrenamiento` WRITE;
/*!40000 ALTER TABLE `registro_entrenamiento` DISABLE KEYS */;
/*!40000 ALTER TABLE `registro_entrenamiento` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `retos`
--

DROP TABLE IF EXISTS `retos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `retos` (
  `id_reto` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_retador` int(10) unsigned NOT NULL,
  `id_retado` int(10) unsigned NOT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `estado` enum('pendiente','aceptado','rechazado') NOT NULL DEFAULT 'pendiente',
  PRIMARY KEY (`id_reto`),
  UNIQUE KEY `no_duplicados` (`id_retador`,`id_retado`),
  KEY `id_retado` (`id_retado`),
  CONSTRAINT `1` FOREIGN KEY (`id_retador`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE,
  CONSTRAINT `2` FOREIGN KEY (`id_retado`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `retos`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `retos` WRITE;
/*!40000 ALTER TABLE `retos` DISABLE KEYS */;
/*!40000 ALTER TABLE `retos` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `rutina`
--

DROP TABLE IF EXISTS `rutina`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rutina` (
  `id_rutina` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `objetivo` enum('fuerza','resistencia','perdida_peso','volumen','flexibilidad') DEFAULT NULL,
  `nivel` enum('principiante','intermedio','avanzado') NOT NULL DEFAULT 'principiante',
  `dias_semana` tinyint(3) unsigned DEFAULT 3,
  `activa` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `generada_ia` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_rutina`),
  KEY `idx_usuario` (`id_usuario`),
  KEY `idx_activa` (`activa`),
  CONSTRAINT `fk_rutina_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rutina`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `rutina` WRITE;
/*!40000 ALTER TABLE `rutina` DISABLE KEYS */;
INSERT INTO `rutina` VALUES
(1,1,'Fuerza Doble Intensidad','Ejercita dos días a la semana con intensidad progresiva para construir fuerza muscular de manera eficiente y sostenible.','fuerza','intermedio',2,1,'2026-10-01 22:47:17','2026-10-01 22:47:17',0),
(2,1,'Fuerza Pura - Rutina Avanzada de 5 Días','Esta rutina está diseñada para desafiar tus límites y construir una fuerza explosiva mediante periodización inteligente.','fuerza','avanzado',5,1,'2026-10-01 22:56:50','2026-10-01 22:56:50',0),
(3,2,'Fuerza Intensiva 2 Días','Rutina de alta intensidad diseñada para maximizar la ganancia muscular en solo dos sesiones semanales, optimizando cada repetición con progresiones inteligentes.','fuerza','intermedio',2,1,'2026-10-13 21:37:23','2026-10-13 21:37:23',0),
(4,2,'Fuerza Total - Cuerpo Completo','Construye una base sólida de fuerza con ejercicios compuestos intensos que desafían tu cuerpo y te empujan hacia nuevos límites, dos días a la semana.','fuerza','intermedio',2,1,'2026-10-13 21:50:44','2026-10-13 21:50:44',0),
(5,3,'Máxima Intensidad: Fuerza Avanzada','Esta rutina está diseñada para atletas avanzados que buscan maximizar su fuerza con sesiones de alta intensidad y volumen controlado durante 5 días a la semana.','fuerza','avanzado',5,1,'2026-10-14 12:31:14','2026-10-14 12:31:14',0),
(6,4,'Fuerza Dual: Torso y Piernas','Esta rutina de dos días diseña fuerza explosiva en cada sesión, alternando tren superior e inferior para maximizar tu progreso sin sobrecargar tu recuperación.','fuerza','intermedio',2,1,'2026-10-16 13:03:47','2026-10-16 13:03:47',0);
/*!40000 ALTER TABLE `rutina` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `rutina_ejercicio`
--

DROP TABLE IF EXISTS `rutina_ejercicio`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rutina_ejercicio` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_rutina` int(10) unsigned NOT NULL,
  `id_ejercicio` int(10) unsigned NOT NULL,
  `dia_semana` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `orden` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `series_objetivo` tinyint(3) unsigned DEFAULT 3,
  `reps_objetivo` tinyint(3) unsigned DEFAULT 10,
  `peso_sugerido` decimal(5,1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rutina_ej_dia` (`id_rutina`,`id_ejercicio`,`dia_semana`),
  KEY `id_ejercicio` (`id_ejercicio`),
  CONSTRAINT `rutina_ejercicio_ibfk_1` FOREIGN KEY (`id_rutina`) REFERENCES `rutina` (`id_rutina`) ON DELETE CASCADE,
  CONSTRAINT `rutina_ejercicio_ibfk_2` FOREIGN KEY (`id_ejercicio`) REFERENCES `ejercicio` (`id_ejercicio`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=83 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rutina_ejercicio`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `rutina_ejercicio` WRITE;
/*!40000 ALTER TABLE `rutina_ejercicio` DISABLE KEYS */;
INSERT INTO `rutina_ejercicio` VALUES
(1,1,53,1,1,4,8,NULL),
(2,1,54,1,2,4,8,NULL),
(3,1,54,2,1,4,8,NULL),
(4,1,53,2,2,4,8,NULL),
(5,2,53,1,1,4,8,NULL),
(6,2,54,1,2,4,8,NULL),
(7,2,54,2,1,4,8,NULL),
(8,2,53,2,2,4,8,NULL),
(9,2,54,3,1,4,8,NULL),
(10,2,53,3,2,4,8,NULL),
(11,2,54,4,1,4,8,NULL),
(12,2,53,4,2,4,8,NULL),
(13,2,54,5,1,4,8,NULL),
(14,2,53,5,2,4,8,NULL),
(15,3,53,1,1,4,8,NULL),
(16,3,60,1,2,4,8,NULL),
(17,3,22,1,3,4,8,NULL),
(18,3,21,1,4,4,8,NULL),
(19,3,54,1,5,4,8,NULL),
(20,3,4,1,6,4,8,NULL),
(21,3,59,2,1,4,8,NULL),
(22,3,61,2,2,4,8,NULL),
(23,3,51,2,3,4,8,NULL),
(24,3,57,2,4,4,8,NULL),
(25,3,50,2,5,4,8,NULL),
(26,3,17,2,6,4,8,NULL),
(27,4,53,1,1,4,8,NULL),
(28,4,60,1,2,4,8,NULL),
(29,4,57,1,3,4,8,NULL),
(30,4,50,1,4,4,8,NULL),
(31,4,54,1,5,4,8,NULL),
(32,4,4,1,6,4,8,NULL),
(33,4,59,2,1,4,8,NULL),
(34,4,51,2,2,4,8,NULL),
(35,4,61,2,3,4,8,NULL),
(36,4,22,2,4,4,8,NULL),
(37,4,21,2,5,4,8,NULL),
(38,4,16,2,6,4,8,NULL),
(39,5,47,1,1,4,8,NULL),
(40,5,53,1,2,4,8,NULL),
(41,5,22,1,3,4,8,NULL),
(42,5,21,1,4,4,8,NULL),
(43,5,20,1,5,4,8,NULL),
(44,5,24,1,6,4,8,NULL),
(45,5,23,1,7,4,8,NULL),
(46,5,55,1,8,4,8,NULL),
(47,5,49,2,1,4,8,NULL),
(48,5,52,2,2,4,8,NULL),
(49,5,59,2,3,4,8,NULL),
(50,5,57,2,4,4,8,NULL),
(51,5,50,2,5,4,8,NULL),
(52,5,15,2,6,4,8,NULL),
(53,5,17,2,7,4,8,NULL),
(54,5,16,2,8,4,8,NULL),
(55,5,60,3,1,4,8,NULL),
(56,5,48,3,2,4,8,NULL),
(57,5,44,3,3,4,8,NULL),
(58,5,46,3,4,4,8,NULL),
(59,5,45,3,5,4,8,NULL),
(60,5,43,3,6,4,8,NULL),
(61,5,5,3,7,4,8,NULL),
(62,5,4,3,8,4,8,NULL),
(63,5,51,4,1,4,8,NULL),
(64,5,61,4,2,4,8,NULL),
(65,5,56,4,3,4,8,NULL),
(66,5,54,4,4,4,8,NULL),
(67,5,37,4,5,4,8,NULL),
(68,5,30,4,6,4,8,NULL),
(69,5,35,4,7,4,8,NULL),
(70,5,36,4,8,4,8,NULL),
(71,5,41,5,1,4,8,NULL),
(72,5,42,5,2,4,8,NULL),
(73,5,38,5,3,4,8,NULL),
(74,5,39,5,4,4,8,NULL),
(75,5,40,5,5,4,8,NULL),
(76,5,8,5,6,4,8,NULL),
(77,5,9,5,7,4,8,NULL),
(78,5,12,5,8,4,8,NULL),
(79,6,53,1,1,4,8,NULL),
(80,6,54,1,2,4,8,NULL),
(81,6,54,2,1,4,8,NULL),
(82,6,53,2,2,4,8,NULL);
/*!40000 ALTER TABLE `rutina_ejercicio` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `salud_notificacion`
--

DROP TABLE IF EXISTS `salud_notificacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `salud_notificacion` (
  `id_notif` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `modulo` enum('psicologia','alimentacion','clinico','general') NOT NULL DEFAULT 'general',
  `tipo` enum('alerta','recordatorio','logro','sugerencia') NOT NULL DEFAULT 'sugerencia',
  `nivel` enum('info','atencion','urgente','emergencia') NOT NULL DEFAULT 'info',
  `titulo` varchar(100) NOT NULL,
  `mensaje` text NOT NULL,
  `leida` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_notif`),
  KEY `idx_usuario_leida` (`id_usuario`,`leida`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `fk_sn_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `salud_notificacion`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `salud_notificacion` WRITE;
/*!40000 ALTER TABLE `salud_notificacion` DISABLE KEYS */;
/*!40000 ALTER TABLE `salud_notificacion` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `sesion`
--

DROP TABLE IF EXISTS `sesion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sesion` (
  `id_sesion` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `id_rutina` int(10) unsigned DEFAULT NULL,
  `fecha` date NOT NULL,
  `duracion_minutos` smallint(5) unsigned DEFAULT NULL,
  `sensacion` enum('muy_mal','mal','regular','bien','muy_bien') DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `volumen_total_kg` decimal(10,2) DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `estado` varchar(20) DEFAULT 'finalizada',
  `nombre_rutina` varchar(120) DEFAULT NULL,
  PRIMARY KEY (`id_sesion`),
  KEY `idx_usuario_fecha` (`id_usuario`,`fecha`),
  KEY `idx_rutina` (`id_rutina`),
  CONSTRAINT `fk_sesion_rutina` FOREIGN KEY (`id_rutina`) REFERENCES `rutina` (`id_rutina`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_sesion_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sesion`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `sesion` WRITE;
/*!40000 ALTER TABLE `sesion` DISABLE KEYS */;
/*!40000 ALTER TABLE `sesion` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `usuario`
--

DROP TABLE IF EXISTS `usuario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuario` (
  `id_usuario` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `peso_kg` decimal(5,2) DEFAULT NULL,
  `altura_cm` smallint(6) DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `avatar_id` varchar(20) DEFAULT 'avatar_01',
  `equipo_disponible` varchar(255) DEFAULT NULL COMMENT 'JSON array de equipo',
  `nivel_entrenamiento` enum('principiante','intermedio','avanzado') DEFAULT 'principiante',
  `objetivo` enum('fuerza','masa_muscular','perdida_grasa','resistencia','volumen','flexibilidad') DEFAULT NULL,
  `sexo_biologico` enum('masculino','femenino') DEFAULT NULL,
  `foto_url` varchar(500) DEFAULT NULL,
  `onboarding_completo` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `fitness_score` tinyint(3) unsigned DEFAULT NULL COMMENT 'Score 0-100 calculado del cuestionario',
  `cuestionario_completo` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = cuestionario respondido',
  `racha_actual` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `uq_email` (`email`),
  KEY `idx_nivel` (`nivel_entrenamiento`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuario`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `usuario` WRITE;
/*!40000 ALTER TABLE `usuario` DISABLE KEYS */;
INSERT INTO `usuario` VALUES
(1,'leo','leo_@gamil.com','$2y$10$bEV.DjdEAZIM.0zKtUIjneGphv0QFWaJZ/rfLS6wtV1dz0NGe/QQe',70.00,170,NULL,'avatar_01','bandas_elasticas','avanzado','fuerza','masculino',NULL,1,'2026-10-01 22:11:50','2026-10-01 22:56:40',80,1,0),
(2,'leo','leo@gamil.com','$2y$10$IUJko4GUHcW8pgD/l0ac8OmMeOE/n8ec3WTJzl3I176qv/P1YjKAC',140.00,170,NULL,'avatar_01','mancuernas','intermedio','fuerza','femenino',NULL,1,'2026-10-13 20:50:07','2026-10-13 21:50:31',30,1,0),
(3,'david','david@gmail.com','$2y$10$Diz78h8.UKeJAWwntAneB.njgqPS2.nSK2ytNcH.kgmhKvbQI5Zx6',85.00,178,NULL,'avatar_01','gimnasio_completo','avanzado','fuerza','masculino',NULL,1,'2026-10-14 12:18:53','2026-10-14 12:31:02',90,1,0),
(4,'Laura','laura@gmail.com','$2y$10$LKuuaek785W06HjSLq1rEOdcouT6PgDeU/7KqocrX7U/VO4TCw4wu',60.00,1,NULL,'avatar_01','bandas_elasticas','intermedio','fuerza','femenino',NULL,1,'2026-10-16 12:50:28','2026-10-16 13:03:32',40,1,0);
/*!40000 ALTER TABLE `usuario` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `usuario_logro`
--

DROP TABLE IF EXISTS `usuario_logro`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuario_logro` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `id_logro` int(10) unsigned NOT NULL,
  `fecha_obtenido` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuario_logro` (`id_usuario`,`id_logro`),
  KEY `fk_ul_logro` (`id_logro`),
  CONSTRAINT `fk_ul_logro` FOREIGN KEY (`id_logro`) REFERENCES `logro` (`id_logro`) ON DELETE CASCADE,
  CONSTRAINT `fk_ul_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuario_logro`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `usuario_logro` WRITE;
/*!40000 ALTER TABLE `usuario_logro` DISABLE KEYS */;
/*!40000 ALTER TABLE `usuario_logro` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `usuario_mision`
--

DROP TABLE IF EXISTS `usuario_mision`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuario_mision` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `id_mision` int(10) unsigned NOT NULL,
  `progreso_actual` smallint(5) unsigned DEFAULT 0,
  `completada` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_usuario_fecha` (`id_usuario`,`fecha_fin`),
  KEY `fk_um_mision` (`id_mision`),
  CONSTRAINT `fk_um_mision` FOREIGN KEY (`id_mision`) REFERENCES `mision` (`id_mision`) ON DELETE CASCADE,
  CONSTRAINT `fk_um_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuario_mision`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `usuario_mision` WRITE;
/*!40000 ALTER TABLE `usuario_mision` DISABLE KEYS */;
/*!40000 ALTER TABLE `usuario_mision` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `usuario_perfil_salud`
--

DROP TABLE IF EXISTS `usuario_perfil_salud`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuario_perfil_salud` (
  `id_perfil` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `tipo_sangre` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') DEFAULT NULL,
  `alergias` text DEFAULT NULL,
  `enfermedades_cronicas` text DEFAULT NULL,
  `medicamentos_actuales` text DEFAULT NULL,
  `antecedentes_familiares` text DEFAULT NULL,
  `condicion_alimentaria` enum('ninguna','vegetariano','vegano','celiaco','diabetico','hipertenso','otro') DEFAULT 'ninguna',
  `alergias_alimentarias` text DEFAULT NULL,
  `meta_nutricional` enum('bajar_peso','subir_peso','mantener','rendimiento','salud_general') DEFAULT 'salud_general',
  `nivel_estres_base` tinyint(3) unsigned DEFAULT NULL COMMENT '0-10 autoreportado',
  `duerme_horas` decimal(3,1) DEFAULT NULL,
  `consume_alcohol` tinyint(1) DEFAULT 0,
  `consume_tabaco` tinyint(1) DEFAULT 0,
  `acepto_consentimiento` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_consentimiento` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_perfil`),
  UNIQUE KEY `uq_usuario` (`id_usuario`),
  CONSTRAINT `fk_ups_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuario_perfil_salud`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `usuario_perfil_salud` WRITE;
/*!40000 ALTER TABLE `usuario_perfil_salud` DISABLE KEYS */;
INSERT INTO `usuario_perfil_salud` VALUES
(1,1,NULL,NULL,NULL,NULL,NULL,'ninguna',NULL,'salud_general',NULL,NULL,0,0,1,'2026-10-01 22:39:27','2026-10-01 22:39:27','2026-10-01 22:56:40'),
(34,2,NULL,NULL,NULL,NULL,NULL,'ninguna',NULL,'salud_general',NULL,NULL,0,0,1,'2026-10-13 21:37:13','2026-10-13 21:37:13','2026-10-13 21:50:31'),
(36,3,NULL,NULL,NULL,NULL,NULL,'ninguna',NULL,'salud_general',NULL,NULL,0,0,1,'2026-10-14 12:31:02','2026-10-14 12:31:02','2026-10-14 12:31:02'),
(37,4,NULL,NULL,NULL,NULL,NULL,'ninguna',NULL,'salud_general',NULL,NULL,0,0,1,'2026-10-16 13:03:32','2026-10-16 13:03:32','2026-10-16 13:03:32');
/*!40000 ALTER TABLE `usuario_perfil_salud` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Temporary table structure for view `v_ultimo_animo`
--

DROP TABLE IF EXISTS `v_ultimo_animo`;
/*!50001 DROP VIEW IF EXISTS `v_ultimo_animo`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_ultimo_animo` AS SELECT
 NULL AS `id_registro`,
 NULL AS `id_usuario`,
 NULL AS `fecha`,
 NULL AS `hora`,
 NULL AS `estado_animo`,
 NULL AS `emoji`,
 NULL AS `factores`,
 NULL AS `nota_libre`,
 NULL AS `created_at` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_ultimo_registro_nutri`
--

DROP TABLE IF EXISTS `v_ultimo_registro_nutri`;
/*!50001 DROP VIEW IF EXISTS `v_ultimo_registro_nutri`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_ultimo_registro_nutri` AS SELECT
 NULL AS `id_registro`,
 NULL AS `id_usuario`,
 NULL AS `fecha`,
 NULL AS `desayuno`,
 NULL AS `comida`,
 NULL AS `cena`,
 NULL AS `snacks`,
 NULL AS `agua_vasos`,
 NULL AS `calorias_estimadas`,
 NULL AS `proteina_g`,
 NULL AS `carbohidratos_g`,
 NULL AS `grasa_g`,
 NULL AS `imc`,
 NULL AS `peso_kg`,
 NULL AS `created_at`,
 NULL AS `updated_at` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_ultimo_signo_vital`
--

DROP TABLE IF EXISTS `v_ultimo_signo_vital`;
/*!50001 DROP VIEW IF EXISTS `v_ultimo_signo_vital`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_ultimo_signo_vital` AS SELECT
 NULL AS `id_signo`,
 NULL AS `id_usuario`,
 NULL AS `fecha`,
 NULL AS `hora`,
 NULL AS `temperatura_c`,
 NULL AS `frecuencia_cardiaca`,
 NULL AS `presion_sistolica`,
 NULL AS `presion_diastolica`,
 NULL AS `saturacion_o2`,
 NULL AS `frecuencia_resp`,
 NULL AS `glucosa_mg_dl`,
 NULL AS `interpretacion`,
 NULL AS `created_at` */;
SET character_set_client = @saved_cs_client;

--
-- Final view structure for view `v_ultimo_animo`
--

/*!50001 DROP VIEW IF EXISTS `v_ultimo_animo`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_uca1400_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`LEO`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_ultimo_animo` AS select `ra`.`id_registro` AS `id_registro`,`ra`.`id_usuario` AS `id_usuario`,`ra`.`fecha` AS `fecha`,`ra`.`hora` AS `hora`,`ra`.`estado_animo` AS `estado_animo`,`ra`.`emoji` AS `emoji`,`ra`.`factores` AS `factores`,`ra`.`nota_libre` AS `nota_libre`,`ra`.`created_at` AS `created_at` from (`psico_registro_animo` `ra` join (select `psico_registro_animo`.`id_usuario` AS `id_usuario`,max(`psico_registro_animo`.`created_at`) AS `ultima` from `psico_registro_animo` group by `psico_registro_animo`.`id_usuario`) `t` on(`ra`.`id_usuario` = `t`.`id_usuario` and `ra`.`created_at` = `t`.`ultima`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_ultimo_registro_nutri`
--

/*!50001 DROP VIEW IF EXISTS `v_ultimo_registro_nutri`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_uca1400_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`LEO`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_ultimo_registro_nutri` AS select `rn`.`id_registro` AS `id_registro`,`rn`.`id_usuario` AS `id_usuario`,`rn`.`fecha` AS `fecha`,`rn`.`desayuno` AS `desayuno`,`rn`.`comida` AS `comida`,`rn`.`cena` AS `cena`,`rn`.`snacks` AS `snacks`,`rn`.`agua_vasos` AS `agua_vasos`,`rn`.`calorias_estimadas` AS `calorias_estimadas`,`rn`.`proteina_g` AS `proteina_g`,`rn`.`carbohidratos_g` AS `carbohidratos_g`,`rn`.`grasa_g` AS `grasa_g`,`rn`.`imc` AS `imc`,`rn`.`peso_kg` AS `peso_kg`,`rn`.`created_at` AS `created_at`,`rn`.`updated_at` AS `updated_at` from (`nutri_registro_diario` `rn` join (select `nutri_registro_diario`.`id_usuario` AS `id_usuario`,max(`nutri_registro_diario`.`fecha`) AS `ultima_fecha` from `nutri_registro_diario` group by `nutri_registro_diario`.`id_usuario`) `t` on(`rn`.`id_usuario` = `t`.`id_usuario` and `rn`.`fecha` = `t`.`ultima_fecha`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_ultimo_signo_vital`
--

/*!50001 DROP VIEW IF EXISTS `v_ultimo_signo_vital`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_uca1400_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`LEO`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_ultimo_signo_vital` AS select `sv`.`id_signo` AS `id_signo`,`sv`.`id_usuario` AS `id_usuario`,`sv`.`fecha` AS `fecha`,`sv`.`hora` AS `hora`,`sv`.`temperatura_c` AS `temperatura_c`,`sv`.`frecuencia_cardiaca` AS `frecuencia_cardiaca`,`sv`.`presion_sistolica` AS `presion_sistolica`,`sv`.`presion_diastolica` AS `presion_diastolica`,`sv`.`saturacion_o2` AS `saturacion_o2`,`sv`.`frecuencia_resp` AS `frecuencia_resp`,`sv`.`glucosa_mg_dl` AS `glucosa_mg_dl`,`sv`.`interpretacion` AS `interpretacion`,`sv`.`created_at` AS `created_at` from (`clinico_signos_vitales` `sv` join (select `clinico_signos_vitales`.`id_usuario` AS `id_usuario`,max(`clinico_signos_vitales`.`created_at`) AS `ultima` from `clinico_signos_vitales` group by `clinico_signos_vitales`.`id_usuario`) `t` on(`sv`.`id_usuario` = `t`.`id_usuario` and `sv`.`created_at` = `t`.`ultima`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

-- Dump completed on 2026-10-21 11:32:13
