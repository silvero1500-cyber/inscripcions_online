<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ajuste;

/**
 * Política de privacitat de la plataforma. El text es desa a `ajustes`
 * (PRIVACIDAD_TEXTO) i, si està buit, es mostra una PLANTILLA RGPD per defecte
 * (amb camps [ENTRE CLAUDÀTORS] que l'organitzador ha d'omplir).
 */
final class PrivacyPolicy
{
    public static function text(): string
    {
        $stored = Ajuste::get(Ajuste::PRIVACIDAD_TEXTO);
        return ($stored !== null && trim($stored) !== '') ? $stored : self::defaultTemplate();
    }

    public static function defaultTemplate(): string
    {
        return <<<TXT
POLÍTICA DE PRIVACITAT

Última actualització: [DATA]

1. RESPONSABLE DEL TRACTAMENT
Responsable: [NOM O RAÓ SOCIAL]
NIF/CIF: [NIF]
Adreça: [ADREÇA POSTAL]
Correu electrònic: [CORREU DE CONTACTE]

2. QUINES DADES TRACTEM
- Dades d'inscripció: nom, cognoms, DNI/NIE, data de naixement, sexe, correu electrònic, telèfon, població, codi postal, club i talla de samarreta.
- Dades de pagament: el pagament es gestiona a través de la passarel·la Redsys. No emmagatzemem les dades de la teva targeta.
- Dades de comandes de la botiga (si compres productes): dades de contacte i detall de la comanda.

3. AMB QUINA FINALITAT
- Gestionar la teva inscripció i participació a l'esdeveniment.
- Tramitar el pagament i emetre el comprovant.
- Gestionar el lliurament de dorsal/material i les comandes de la botiga.
- Enviar-te comunicacions relacionades amb la teva inscripció o comanda.

4. BASE JURÍDICA
- L'execució del contracte (la teva inscripció o compra).
- El teu consentiment, que pots retirar en qualsevol moment.
- El compliment d'obligacions legals (per exemple, fiscals i comptables).

5. DURANT QUANT DE TEMPS
Conservem les teves dades mentre duri la relació i, després, durant els terminis legalment exigibles per a possibles responsabilitats.

6. A QUI COMUNIQUEM LES DADES
- Passarel·la de pagament (Redsys) per processar el cobrament.
- Proveïdors tecnològics necessaris (allotjament web i enviament de correu electrònic).
- Administracions públiques quan hi hagi una obligació legal.
No cedim les teves dades a tercers amb finalitats comercials sense el teu consentiment.

7. ELS TEUS DRETS
Pots exercir els drets d'accés, rectificació, supressió, oposició, limitació del tractament i portabilitat enviant un correu a [CORREU DE CONTACTE], indicant el dret que vols exercir.
També pots presentar una reclamació davant l'Agència Espanyola de Protecció de Dades (www.aepd.es) si consideres que el tractament no s'ajusta a la normativa.

8. SEGURETAT
Apliquem mesures tècniques i organitzatives raonables per protegir les teves dades davant l'accés no autoritzat, la pèrdua o l'alteració.

[Revisa i adapta aquest text a la teva realitat. Recomanem una revisió jurídica abans de publicar-lo.]
TXT;
    }
}
