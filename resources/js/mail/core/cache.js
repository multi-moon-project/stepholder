export function setLimitedMap(map, key, value, max = 40) {
  if (map.has(key)) {
    map.delete(key);
  }

  map.set(key, value);

  while (map.size > max) {
    const firstKey = map.keys().next().value;
    map.delete(firstKey);
  }
}

export function clearMap(map) {
  map.clear();
}