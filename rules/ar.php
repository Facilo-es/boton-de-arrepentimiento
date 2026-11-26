<?php
/**
 * Argentine rules. Another country needs a new file like this one and little else.
 *
 * Free reads the labels, hours and retention. Only Pro reads term_days and non_working.
 *
 * Non-working days: national holidays and the national tourism days for 2026.
 * Source: official calendar at https://www.argentina.gob.ar/jefatura/feriados-nacionales-2026,
 * Ley 27.399, Resolución 164/2025 (tourism days 23 Mar, 10 Jul and 7 Dec) and Decreto 1103/2026
 * (9 Nov, visit of Pope Leo XIV). Checked on 7 Nov 2026. Regional dates, such as 10 Nov in CABA and
 * Córdoba or 11 Nov in Buenos Aires province, are left out: owners add them in Ajustes.
 *
 * @package FaciloBotonArrepentimiento
 */

defined( 'ABSPATH' ) || exit;

return array(
	'timezone'        => 'America/Argentina/Buenos_Aires',
	'term_days'       => 10,
	'reply_hours'     => 24,
	'proof_hours'     => 72,
	'event_hours'     => 24,
	'labels'          => array(
		'arrepentimiento' => 'BOTÓN DE ARREPENTIMIENTO',
		'baja'            => 'BOTÓN DE BAJA DE SERVICIO',
	),
	'non_working'     => array(
		'2026-01-01', // Año Nuevo.
		'2026-02-16', // Carnaval.
		'2026-02-17', // Carnaval.
		'2026-03-23', // Día no laborable con fines turísticos.
		'2026-03-24', // Día de la Memoria por la Verdad y la Justicia.
		'2026-04-02', // Veteranos y Caídos en Malvinas.
		'2026-04-03', // Viernes Santo.
		'2026-05-01', // Día del Trabajador.
		'2026-05-25', // Revolución de Mayo.
		'2026-06-15', // Güemes, trasladado del 17.
		'2026-06-20', // Belgrano.
		'2026-07-09', // Independencia.
		'2026-07-10', // Día no laborable con fines turísticos.
		'2026-08-17', // San Martín.
		'2026-10-12', // Diversidad cultural.
		'2026-11-09', // Feriado nacional, visita del Papa León XIV.
		'2026-11-23', // Soberanía Nacional, trasladado del 20.
		'2026-12-07', // Día no laborable con fines turísticos.
		'2026-12-08', // Inmaculada Concepción.
		'2026-12-25', // Navidad.
	),
	'retention_years' => 3,
);
