#!/usr/bin/perl -i -p
BEGIN { $fixed = 0; }
if (!$fixed && /let cum = baseline\[s\]/) {
    $fixed = 1;
    s/let cum = baseline\[s] \|\| 0;/\/\/ Start from 0 for the selected month only (not cumulative with previous months)/;
    $_ .= <>;
    s/cum \+= \(raw\[w\] && raw\[w\]\[s\]\) \? parseInt\(raw\[w\]\[s\]\) : 0;\n\s*return cum;/return (raw\[w\] && raw\[w\]\[s\]) ? parseInt(raw\[w\]\[s\]) : 0;/;
}
