��    T      �  q   \         	   !     +     >     Q  
   d  )   o  "   �     �  &   �  �   �     v     �  5   �  �   �  N   m	  Y   �	     
     
  t   -
  ?   �
     �
     �
     �
                      e  5     �     �     �     �     �     �  K     	   a     k     z     �  �   �  �   >     �     �  g   �  �   H  �     	   �  D  �  R   ;  g   �     �  �   	    �  �    �   �  �   �     L      P   �   e   q   J!     �!     �!     �!     �!     �!     �!      �!     "     "     "     ""     *"     /"  8   6"  5   o"     �"  :   �"     �"  B   �"     =#     F#     M#     U#  m  ]#     �$     �$     �$     %  
   %  -   $%  3   R%     �%  )   �%  �   �%     J&     ^&  !   r&  �   �&  \   Z'  l   �'     $(     -(  f   E(  D   �(     �(     �(  %   �(     ")     1)     7)     @)  �  W)     �+     ,     ,     $,     B,     J,  F   j,     �,     �,     �,     �,  �   �,  �   �-     M.     ^.  �   e.  �   �.  (  �/     1  ^  1  R   r2  �   �2     O3  �   c3  H  ]4  �  �8    j=  }   v>     �>     �>  �   ?  x   �?     H@     L@     U@     a@     g@     n@  #   s@     �@     �@     �@     �@     �@     �@  /   �@  9   �@  "   /A  B   RA     �A  A   �A     �A     �A     �A     �A                E   5   ,   P   !   3   D       /           S      4          &             L   6              )          J              2   +      :   >   =           '      <       "      G      O   -       *   .          0                         (                 C   ?   %       @      ;      H   
   T   $   R            I   8      1       A   K           9   B       #      N      F      	             7       Q   M    Add Queue Agent Announce Msg Agent Regex Filter Agent Restrictions Alert Info Announce position of caller in the queue? Bad Queue Number, can not be blank Call as Dialed Compound Recordings in Queues Detected Earlier versions of this module allowed such queues to be chosen, once changing this setting, it will no longer appear as an option Extensions Only Fail Over Destination Give this queue a brief name to help you identify it. Gives queues a 'weight' option, to ensure calls waiting in a higher priority queue will deliver its calls first if there are agents common to both queues. How often to announce a voice menu to the caller (0 to Disable Announcements). How often to announce queue position and estimated holdtime (0 to Disable Announcements). INUSE IVR Announce: %s If you wish to report the caller's hold time to the member before they are connected to the caller, set this to yes. Maximum number of people waiting in the queue (0 for unlimited) Menu ID  No No Follow-Me or Call Forward No Retry None Once Periodic Announcements Provides an optional regex expression that will be applied against the agent callback number. If the callback number does not pass the regex filter then it will be treated as invalid. This can be used to restrict agents to extensions within a range, not allow callbacks to include keys like *, or any other use that may be appropriate. An example input might be:<br />^([2-4][0-9]{3})$<br />This would restrict agents to extensions 2000-4999. Or <br />^([0-9]+)$ would allow any number of any length, but restrict the * key.<br />WARNING: make sure you understand what you are doing or otherwise leave this blank! Queue Queue %s : %s Queue - %s (%s): %s<br /> Queue Number must not be blank Queue Weight Queue calls only (ringinuse=no) Queue name must not be blank and must contain only alpha-numeric characters Queue: %s Queue: %s (%s) Queues Restrict Dynamic Agents Restrict dynamic queue member logins to only those listed in the Dynamic Members list above. When set to Yes, members not listed will be DENIED ACCESS to the queue. Should we include estimated hold time in position announcements?  Either yes, no, or only once; hold time will not be announced if <1 minute Static Agents Strict The maximum number of seconds a caller can wait in a queue before being pulled out.  (0 for unlimited). The number of seconds an agent's phone can ring before we consider it a timeout. Unlimited or other timeout values may still be limited by system ringtime or individual extension defaults. The number of seconds we wait before trying all the phones again. Choosing "No Retry" will exit the Queue and go to the fail-over destination as soon as the first attempted agent times-out, additional agents will not be attempted. Unlimited Use this number to dial into the queue, or transfer callers to this number to put them into the queue.<br><br>Agents will dial this queue number plus * to log onto the queue, and this queue number plus ** to log out of the queue.<br><br>For example, if the queue number is 123:<br><br><b>123* = log in<br>123** = log out</b> Used for service level statistics (calls answered within service level time frame) Using a Regex filter is fairly advanced, please confirm you know what you are doing or leave this blank Warning! Extension Warning, there are compound recordings configured in one or more Queue configurations. Queues can not play these so they have been truncated to the first sound file. You should correct this problem.<br />Details:<br /><br /> When set to 'Call as Dialed' the queue will call an extension just as if the queue were another user. Any Follow-Me or Call Forward states active on the extension will result in the queue call following these call paths. This behavior has been the standard queue behavior on past FreePBX versions. <br />When set to 'No Follow-Me or Call Forward', all agents that are extensions on the system will be limited to ringing their extensions only. Follow-Me and Call Forward settings will be ignored. Any other agent will be called as dialed. This behavior is similar to how extensions are dialed in ringgroups. <br />When set to 'Extensions Only' the queue will dial Extensions as described for 'No Follow-Me or Call Forward'. Any other number entered for an agent that is NOT a valid extension will be ignored. No error checking is provided when entering a static agent or when logging on as a dynamic agent, the call will simply be blocked when the queue tries to call it. For dynamic agents, see the 'Agent Regex Filter' to provide some validation. When set to 'Yes' agents who are on an occupied phone will be skipped as if the line were returning busy. This means that Call Waiting or multi-line phones will not be presented with the call and in the various hunt style ring strategies, the next agent will be attempted. <br />When set to 'Yes + (ringinuse=no)' the queue configuration flag 'ringinuse=no' is set for this queue in addition to the phone's device status being monitored. This results in the queue tracking remote agents (agents who are a remote PSTN phone, called through Follow-Me, and other means) as well as PBX connected agents, so the queue will not attempt to send another call if they are already on a call from any queue. <br />When set to 'Queue calls only (ringinuse=no)' the queue configuration flag 'ringinuse=no' is set for this queue also but the device status of locally connected agents is not monitored. The behavior is to limit an agent belonging to one or more queues to a single queue call. If they are occupied from other calls, such as outbound calls they initiated, the queue will consider them available and ring them since the device state is not monitored with this option. <br /><br />WARNING: When using the settings that set the 'ringinuse=no' flag, there is a NEGATIVE side effect. An agent who transfers a queue call will remain unavailable by any queue until that call is terminated as the call still appears as 'inuse' to the queue UNLESS 'Agent Restrictions' is set to 'Extensions Only'. When set to Yes, the CID Name will be prefixed with the total wait time in the queue so the answering agent is aware how long they have waited. It will be rounded to the nearest minute, in the form of Mnn: where nn is the number of minutes. When this option is set to YES, the following manager events will be generated: AgentCalled, AgentDump, AgentConnect and AgentComplete. Yes Yes + (ringinuse=no) You can optionally present an existing IVR as a 'break out' menu.<br><br>This IVR must only contain single-digit 'dialed options'. The Recording set for the IVR will be played at intervals specified in 'Repeat Frequency', below. You can require agents to enter a password before they can log in to this queue.<br><br>This setting is optional. day default fewestcalls hour hours inherit is not allowed for your account. leastrecent linear minute minutes none random ring agent which was least recently called by this queue ring all available agents until one answers (default) ring random agent ring the agent with fewest completed calls from this queue ringall round robin with memory, remember where we left off last ring pass rrmemory second seconds wrandom Project-Id-Version: FreePBX queues
Report-Msgid-Bugs-To: 
POT-Creation-Date: 2017-10-24 12:55-0700
PO-Revision-Date: 2011-04-02 23:37+0200
Last-Translator: Mikael Carlsson <mickecamino@gmail.com>
Language-Team: 
Language: 
MIME-Version: 1.0
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 8bit
X-Poedit-Language: Swedish
X-Poedit-Country: SWEDEN
 Lägg till kö Meddelande för agent Regexfilter för agent: Agentrestriktion Alert Info Meddela position i kön för dom som väntar? Felaktigt könummer, detta fält kan inte vara tomt Ring som vanligt Upptäckte sammanslagen inspelning i kö  Tidigare versioner av denna modul tillät att sådana köer valdes, så for du ändrar inställning kommer dessa val inte att visas mer. Endast anknytningar Destination vid fel Ge denna kö ett kortfattat namn. Ger kön en vikt, detta för att garantera att samtal som väntar i en kö med högre prioritet kommer att få sina samtal  levererade först om det finns agenter som är anslutna till flera köer. Hur ofta ska menymeddelandet spelas upp för uppringaren (0 för att stänga av meddelanden) Hur ofta meddelandet om köposition och förväntad väntetid ska sägas (0 för att stänga av meddelanden) ANVÄNDS Meddelande för IVR: %s Sätt detta till Ja om du vill meddela uppringarens väntetid till agenten innan samtalet kopplas fram Maximalt antal uppringare som kan vänta i kön (0 för obegränsat) MenyID Nej Ignorera Följ-mig och Vidarekoppling Återförsök: Ingen En gång Periodiska meddelanden Denna text är luddig och översätts senare till Svenska.<br><br>Provides an optional regex expression that will be applied against the agent callback number. If the callback number does not pass the regex filter then it will be treated as invalid. This can be used to restrict agents to extensions within a range, not allow callbacks to include keys like *, or any other use that may be appropriate. An example input might be:<br />^([2-4][0-9]{3})$<br />This would restrict agents to extensions 2000-4999. Or <br />^([0-9]+)$ would allow any number of any length, but restrict the * key.<br />WARNING: make sure you undertand what you are doing or otherwise leave this blank! Kö Kö %s : %s Kö - %s (%s): %s<br> Könummer får inte vara tomt Kövikt Endast kösamtal (ringinuse=no) Könamnet kan inte vara tomt och får bara innehålla a-z, A-Z och 0-9 Kö: %s Kö: %s (%s) Köer Spärra dynamiska ageneter Om detta val sätts till Ja kommer systemet bara att tillåta dynamisk inlogging från ageneter i listan ovan. Agenter som inte är listade kommer att nekas tillgång till kön Ska den uppskattade väntetiden meddelas när positionen meddelas? Antingen ja, nej eller endast en gång, väntetiden kommer inte att meddelas om det är mindre än en minut kvar. Statiska agenter Strikt Det antal sekunder en uppringare kan vänta i kön innan dom kopplas vidare i systemet, om detta är definierat. (0 betyder obegränsat). Det antal sekunder en agents telefon kan ringa innan vi förmodar att väntetiden gått ut. Obegränsat eller andra värden på väntetiden kan vara begränsade av systemets ringtid eller av individuella inställningar på anknytningarna. Det antal sekunder vi väntar före vi försöker ringa alla telefoner igen. Väljs "Inget återförsök" kommer samtalet att avslutas från kön och nästa destination kommer att väljas så fort det första försöket kommer till väntetidens slut, andra agenter kommer inte att försöka nås. Obegränsat Använd detta nummer för att ringa till kön, eller genom att koppla vidare en uppringare till detta nummer, placera denna uppringare i kön.<br><br>Agenter ringer detta nummer plus en * för att logga in i kön, och detta nummer plus ** för att logga ut.<br><br>T.ex. om numret till kön är 123:<br><br>123* = logga in<br><br>123** = logga ut<br> Används till statistik för servicenivå (samtal som besvaras inom servicenivån) Att använda Regexfilter är ganska avancerat, du måste veta vad du gör innan du skriver in värde här, annars lämna detta fält tomt Varning! Anknytning Varning, det finns hopslagna inspelningar i en eller flera kökonfigurationer. Köer kan inte spela upp sammanslagna inspelningar så dom har rundats av till att bara innefatta den första ljudfilen. Du måste rätta till detta.<br>Detaljer:<br><br> Om 'Ring som vanligt' är valt kommer kön att ringa anknytningen precis som om kön var en vanlig användare. Om Följ-mig eller Vidarekoppling är påslaget på anknytningen kommer samtalet att följa dessa. Detta har varit standard för alla tidigare versioner av FreePBX.<br />Om 'Ignorera Följ-mig och Vidarekoppling' är valt kommer alla agenter som är anknytningar att begränsas till att endast ringa anknytningen. Följ-mig och Vidarekoppling kommer att ignoreras. Alla andra agenter kommer att ringa som vanligt. Detta följer inställningen för hur samtal kopplas i  Ringgrupper. <br />Om 'Endast anknytningar' är valt kommer kön att ringa anknytningar enligt 'Ignorera Följ-mig och Vidarekoppling'. Alla andra nummer som är inmatade för en agent, som INTE är en giltig anknytning, kommer att ignoreras. Ingen felkontroll sker när statiska agenter skrivs in eller när en dynamsik agent loggar in i kön, samtalet kommer helt enkelt att blockeras när kön försöker ringa det. För dynamiska agenter kan 'Regexfilter för agent' användas för att ge en form av validering. Om 'Ja' är valt kommer agenter som är upptagna att returnera upptaget. Detta innebär att Samtal väntar eller fler-linjerstelefoner inte kommer att användas och för diverse ringstrategier, nästa agent söks. <br />Om 'Ja + (ringinuse=no)' är valt kommer konfigurationsflaggan 'ringinuse=no' sättas för denna kö som tillägg till telefonens statusbevakning. Detta innebär att kön kommer att bevaka fjärragenter (agenter som är kopplade via externa nummer genom Följ-mig etc) såväl som direktkopplade agenter, kön kommer inte att försöka skicka samtal till upptagna agenter.<br />Om 'Endast kösamtal (ringinuse=no)' är valt kommer konfigurationsflaggan 'ringinuse=no' sättas för denna kö men igen bevakning av enheter för lokala agenter sker. Detta är för att agenter som tillhör flera köer endast ska ska få ett kösamtal. Om agenten är upptagen av annan samtalstyp, de har ringt utgående samtal, kommer kön att betrakta dom som tillgängliga eftersom bevakning av enheten inte sker. <br /><br />VARNING: Det finns en NEGATIV verkan om 'ringinuse=no' är valt. En agent som vidarekopplar ett samtal kommer att vara otillgänglig för kön tills det vidarekopplade samtalet har avslutat. När detta sätts till Ja, kommer ett prefix att sättas på nummerpresentationen med den totala väntetiden i kön så att agenten kan se hur länge samtalet väntat i kön. Detta värde är avrundat till närmsta minut, formatet är Mnn: där nn är antalet minuter När detta sätts till Ja, kommer följande händelser att genereras: AgentCalled, AgentDump, AgentConnect och AgentComplete. Ja Ja + (ringinuse=no) Du kan valfritt ange en IVR som an utbrytningspunkt.<br><br>Denna IVR kan bara innehålla ensiffriga val. Meddelandet för denna IVR kommer att spelas upp periodiskt enligt tiden som anges nedan. Du kan kräva att agenter måste ange ett lösenord innan dom kan logga in i kön.<br><br>Denna inställning är valfri. dag standard fewestcalls timme timmar ärv är inte tillåtet för ditt konto. leastrecent linear minut minuter ingen random ring på agenter som tagit minst samtal nyligen Ring på alla anknytningar tills någon svarar (standard) ring anknytningarna slumpmässigt  ring på agenten med minsta antalet genomförda samtal i denna kö ringall rundringning med minne, kom ihåg var det sista samtalet svarades rrmemory sekund sekunder wrandom 