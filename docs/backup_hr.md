# Integracija backupa

Email daje provider strukturirane konfiguracije `email`. Arhivira prijenosne postavke poput uključenosti, oblika SMTP transporta, pošiljatelja, URL-a aplikacije, integracije obavijesti, postavki workera i menija.

Email outbox i pokušaji dostave namjerno se izostavljaju kako se stare poruke nakon povrata ne bi ponovno poslale. Tajne okruženja i vjerodajnice vezane uz host i dalje treba sigurno zadati na ciljnoj instalaciji; šifrirani backup štiti arhivu u prijenosu, ali tajnu okruženja ne pretvara u prijenosnu postavku.

Koristite component scope za prijenos samo Email postavki ili site scope kao dio potpunog povrata. Nakon povrata, a prije uključivanja produkcijskog workera, pošaljite testnu poruku.
