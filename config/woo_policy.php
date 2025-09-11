<?php
return [
  // Preise nie schreiben (manuell gepflegt)
  'never_write' => ['regular_price', 'sale_price', '_regular_price', '_sale_price', '_price', 'min_price', 'max_price'],

  // erstmal keine „no“-Listen (alles erlaubt) – kannst du später füllen oder via JSON baken
  'manufacturer_allow' => [/* 'wc_field' => ['edelrid'=>true,'petzl'=>false,…] */],

  // veraltete Attribute (ohne pa_-Prefix in Klammern) – optional
  'legacy_attributes' => [],
];
