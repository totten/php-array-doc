<?php

return [
  'null value' => foo(NULL),
  'double value' => foo(2.34),
  'stringy double value' => foo('2.34'),
  'empty string' => foo(''),
  'array 1 2 3' => foo([1, 2, 3]),
  'empty array' => foo([]),
];
