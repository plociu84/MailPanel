<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /**
     * Reguły klucza wpisu mapy Postfixa (lewa kolumna pliku, np. `/etc/postfix/valiastable`).
     *
     * Białe oraz sterujące znaki są niedozwolone: plik mapy jest zapisywany w całości,
     * więc nowa linia w kluczu dopisałaby dodatkowy wpis (albo zepsuła tabelę przed `postmap`).
     */
    protected const MAP_KEY_RULES = 'string|not_regex:/[\x00-\x1F\x7F]/|regex:/^\S+$/';

    /**
     * Reguły wartości wpisu mapy Postfixa (prawa kolumna pliku).
     *
     * Spacje i przecinki są dozwolone (np. `rafalbielawski, kadrytelmor`), znaki sterujące nie.
     */
    protected const MAP_VALUE_RULES = 'string|not_regex:/[\x00-\x1F\x7F]/';
}
