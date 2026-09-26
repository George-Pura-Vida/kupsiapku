"""Single HTML header template. Run after editing navigation; --check detects drift."""
from pathlib import Path
from html import escape
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
LEGAL = {'bezpecnost.html', 'cookies.html', 'obchodni-podminky.html', 'ochrana-osobnich-udaju.html', '404.html'}
HEADER_EXEMPT = {'test-moje-finance.html'}
LANGUAGE_PAIRS = {
    'moje-finance.html': 'my-finances.html',
    'my-finances.html': 'moje-finance.html',
}

def language_counterpart(path, en):
    """Return the translated counterpart path, including pages whose filenames differ."""
    other_name = LANGUAGE_PAIRS.get(path.name, path.name)
    return ROOT / other_name if en else ROOT / 'en' / other_name

def render(path):
    en = path.parent.name == 'en'
    name = path.name
    home = '/en/index.html' if en else '/index.html'
    section = lambda key: ('' if name == 'index.html' else home) + '#' + key
    text = lambda cs, eng: eng if en else cs
    apps = (section('apps'), text('Aplikace', 'Apps'))
    order = (section('order' if en else 'checkout'), text('Objednat', 'Order'))
    faq = (section('faq'), 'FAQ')
    how = (section('how' if en else 'jak-to-funguje'), text('Jak to funguje', 'How it works'))
    base = '/en/' if en else '/'
    refer = (base+'doporucit.html', text('Doporučit a vydělat', 'Refer & earn'))
    account = (base+'ucet.html', text('Můj účet', 'My account'))
    donate = ('/prispet.html', 'Přispět')
    if name == 'index.html':
        links = [apps, (section('gallery'),text('Ukázky','Gallery')), how, faq, (section('about'),text('O mně','About')), refer]
        links += [order, account] if en else [donate, order]
    elif name.startswith('aplikace-'):
        links = [(section('apps'),text('Všechny aplikace','All apps')), order]
    elif name in LEGAL: links = [apps, faq, order]
    elif name == 'doporucit.html': links = [apps, account] if en else [apps, how, faq, donate, order]
    elif name == 'prispet.html': links = [apps, refer, donate]
    elif name == 'admin.html': links = [apps]
    else: links = [apps, refer, account]
    items=[]
    for href,label in links:
        current = ' aria-current="page"' if href == base+name else ''
        items.append(f'<a href="{href}"{current}>{escape(label)}</a>')
    if name == 'index.html' and not en:
        items.append('<a class="siteHeader-cart" href="#checkout"><span aria-hidden="true">🛒</span><span class="siteHeader-sr">Košík, počet aplikací:</span> <span id="count" aria-live="polite">0</span></a>')
    if name == 'admin.html':
        items += ['<strong>Administrace</strong>', '<button id="logout" class="btn alt hide" type="button">Odhlásit</button>']
    other = language_counterpart(path, en)
    language=''
    if other.exists():
        for lang, flag, label in [('cs','CZ','CZ'),('en','GB','EN')]:
            content=f'<span class="siteHeader-flag siteHeader-flag{flag}" aria-hidden="true"></span>{label}'
            if (lang=='en') == en: language+=f'<span aria-current="true">{content}</span>'
            else:
                if name in LANGUAGE_PAIRS:
                    href = ('/' + LANGUAGE_PAIRS[name]) if en else ('/en/' + LANGUAGE_PAIRS[name])
                else:
                    href='/'+name if en else '/en/'+name
                target=f' data-lang-target="{lang}"' if name=='index.html' else ''
                language+=f'<a href="{href}" lang="{lang}" hreflang="{lang}" aria-label="{ "Česká verze" if en else "English version" }"{target}>{content}</a>'
        language=f'<div class="siteHeader-languages" aria-label="{text("Jazyk","Language")}">{language}</div>'
    return '\n'.join(line.rstrip() for line in f'''<header class="siteHeader">
  <div class="siteHeader-inner">
    <a class="siteHeader-brand" href="{'/en/' if en else '/'}"><span aria-hidden="true">🚀</span> KUP SI <b>APKU</b></a>
    {language}
    <button class="siteHeader-toggle" type="button" aria-expanded="false" aria-controls="site-navigation" aria-label="{text('Otevřít menu','Open menu')}" hidden><span aria-hidden="true">☰</span></button>
    <nav class="siteHeader-nav" id="site-navigation" aria-label="{text('Hlavní navigace','Main navigation')}">
      {(chr(10)+'      ').join(items)}
    </nav>
  </div>
</header>'''.splitlines())

def main():
    changed=[]
    for path in sorted(ROOT.rglob('*.html')):
        if path.name in HEADER_EXEMPT:
            continue
        source=path.read_text(encoding='utf-8')
        result,count=re.subn(r'<header\b.*?</header>', lambda _:render(path),source,count=1,flags=re.S)
        assert count==1,path
        if source!=result:
            changed.append(str(path.relative_to(ROOT)))
            if '--check' not in sys.argv: path.write_text(result,encoding='utf-8',newline='\n')
    if '--check' in sys.argv and changed: raise SystemExit('Outdated headers: '+', '.join(changed))
    print(f'Headers {"checked" if "--check" in sys.argv else "updated"}: {len(list(ROOT.rglob("*.html")))} pages')

if __name__=='__main__': main()
