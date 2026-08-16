# Printer status

Easy Print reads the selected queue with `lpstat -l -p QUEUE` and checks whether it accepts work with `lpstat -a QUEUE`. The queue identifier must exist in the latest CUPS queue snapshot before either command runs, and every command argument remains separate.

The official [`lpstat` manual](https://openprinting.github.io/cups/cups-local/lpstat.html) documents long printer listings and accepting-state queries. The current [OpenPrinting CUPS implementation](https://github.com/OpenPrinting/cups/blob/master/systemv/lpstat.c#L1450-L1867) obtains `printer-state-reasons` and prints them in the long listing as `Alerts`. CUPS also documents standard reason keywords such as `media-needed`, `cover-open`, `paused`, and `timed-out` in its [filter and backend programming guide](https://openprinting.github.io/cups/doc/api-filter.html).

## Presentation rules

- Known reason keywords map to concise Portuguese and English guidance.
- Standard severity suffixes (`-error`, `-warning`, and `-report`) do not change the guidance key.
- Unknown but syntactically safe keywords remain visible for diagnosis.
- Unsafe, excessive, empty, or mismatched output becomes a malformed CUPS response.
- The UI never invents ink, toner, or other consumable percentages. It only reports a generic low or empty condition when CUPS advertises one.

The server-rendered fragment refreshes every five seconds while processing, every ten seconds for other available states, and every twenty seconds when the dependency is unavailable. The endpoint is read-only and provides no CUPS administration action.
