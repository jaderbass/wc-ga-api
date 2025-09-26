<?php

namespace App\Services\Woo;

use App\Models\Shop;

/**
 * Class WooRepositoryFactory
 *
 * Zweck:
 * - Erzeugt für einen gegebenen Shop eine konkrete Woo-Repository-Instanz,
 *   die vom WooParentResolver und vom Orchestrator genutzt werden kann.
 * - Kapselt dabei die Erstellung des Low-Level-Clients (WooClient) und gibt
 *   ein Repository auf dessen Basis zurück (WooApiRepository).
 *
 * Verwendung:
 *   $factory   = app(\App\Services\Woo\WooRepositoryFactory::class);
 *   $repo      = $factory->make($shop); // $shop ist Instanz von App\Models\Shop
 *   $resolver  = new \App\Services\Woo\WooParentResolver($repo);
 *   $result    = $resolver->resolve($candidate);
 *
 * Hinweise:
 * - Diese Factory ist absichtlich schlank gehalten, ohne Service-Provider-Registrierung.
 *   So kannst Du pro Shop (Mandant) gezielt eine Repo-Instanz erzeugen.
 * - Falls Du später DI/Bindings bevorzugst, kann zusätzlich ein Service Provider
 *   erstellt werden, der diese Factory im Container registriert.
 *
 * @package App\Services\Woo
 */
class WooRepositoryFactory
{
  /**
   * Erzeugt eine Repository-Instanz für den übergebenen Shop.
   *
   * @param  Shop $shop  Shop mit base_url, api_version, consumer_key, consumer_secret
   * @return WooApiRepository
   */
  public function make(Shop $shop): WooApiRepository
  {
    $client = new WooClient($shop);
    return new WooApiRepository($client);
  }
}
