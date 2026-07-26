<?php

namespace App\Notification;

use App\Entity\Notification;

/**
 * Canal d'acheminement d'une notification.
 *
 * Le cahier des charges (section 20) demande de preparer l'architecture pour
 * l'email, le SMS, WhatsApp et les operateurs de paiement mobile, mais
 * interdit de simuler ces integrations sans API officielle. Ce contrat est
 * donc le point d'extension : ajouter un canal consiste a implementer cette
 * interface, elle sera automatiquement prise en compte grace au tag declare
 * dans config/services.yaml.
 *
 * Aujourd'hui, seul le canal base de donnees existe reellement.
 */
interface NotificationChannelInterface
{
    /**
     * Un canal indisponible (identifiants absents, coordonnees manquantes)
     * doit repondre false plutot que d'echouer : l'acheminement sur un canal
     * ne doit jamais faire echouer l'operation metier qui l'a declenche.
     */
    public function supports(Notification $notification): bool;

    public function send(Notification $notification): void;
}
