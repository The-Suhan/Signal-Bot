import { ref } from 'vue';

// Modül seviyesinde tek bir reaktif kaynak (singleton) — hangi bileşen
// useDarkMode() çağırırsa çağırsın aynı state'i paylaşır. Başlangıç değeri,
// app.blade.php'deki senkron script'in <html> öğesine zaten eklediği/
// eklemediği "dark" class'ından okunur (FOUC önleyici script ile tutarlı
// tek kaynak).
const isDark = ref(document.documentElement.classList.contains('dark'));

function apply(dark) {
    document.documentElement.classList.toggle('dark', dark);
    localStorage.setItem('theme', dark ? 'dark' : 'light');
    isDark.value = dark;
}

function toggle() {
    apply(!isDark.value);
}

// Kullanıcı hiç manuel seçim yapmadıysa (localStorage'da 'theme' yoksa)
// işletim sistemi/tarayıcı temasını canlı takip et. Manuel bir seçim
// yapıldıktan sonra (localStorage'da değer varsa) bu üstün gelir ve sistem
// değişikliği artık otomatik uygulanmaz.
if (window.matchMedia) {
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
        if (!localStorage.getItem('theme')) {
            document.documentElement.classList.toggle('dark', e.matches);
            isDark.value = e.matches;
        }
    });
}

export function useDarkMode() {
    return { isDark, toggle };
}
