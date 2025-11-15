document.addEventListener('mouseover', function(e) {
  if (e.target.classList.contains('linked-biography')) {
    const nom = e.target.dataset.nom;
    let bubble = document.getElementById('bio-infobubble');
    if (!bubble) {
      bubble = document.createElement('div');
      bubble.id = 'bio-infobubble';
      bubble.style.position = 'absolute';
      bubble.style.background = '#fff';
      bubble.style.border = '2px solid #007bff';
      bubble.style.borderRadius = '8px';
      bubble.style.padding = '12px 16px';
      bubble.style.zIndex = 10000;
      bubble.style.maxWidth = '320px';
      bubble.style.boxShadow = '0 2px 14px #3335';
      bubble.style.fontSize = '1.05em';
      bubble.style.cursor = 'default';
      bubble.onmouseenter = function() { bubble.dataset.locked = "1"; };
      bubble.onmouseleave = function() { bubble.style.display = "none"; bubble.dataset.locked = ""; };
      document.body.appendChild(bubble);
    }
    bubble.innerHTML = `
      <div style="margin-bottom:10px;">
        <span style="font-size:1.1em;color:#007bff;font-weight:bold;">${nom}</span>
      </div>
      <button id="popup-recherche-btn"
        style="display:block; margin:0 auto; padding:7px 18px; border-radius:17px; border:none; background:#007bff; color:white; font-size:1em; cursor:pointer;">
        Afficher dans la recherche
      </button>
    `;
    bubble.style.display = 'block';
    const rect = e.target.getBoundingClientRect();
    bubble.style.top = (rect.bottom + window.scrollY + 8) + "px";
    bubble.style.left = (rect.left + window.scrollX) + "px";
    bubble.querySelector('#popup-recherche-btn').onclick = function(ev) {
      window.location.href = '/search.php?q=' + encodeURIComponent(nom);
    };
  }
});

// Le popup reste si la souris passe dessus, disparait si elle sort du mot ET du popup
document.addEventListener('mouseout', function(e) {
  if (e.target.classList.contains('linked-biography')) {
    let bubble = document.getElementById('bio-infobubble');
    setTimeout(function(){
      if (bubble && bubble.dataset.locked !== "1") bubble.style.display = 'none';
    }, 200);
  }
});
window.addEventListener('scroll', function() {
  let bubble = document.getElementById('bio-infobubble');
  if (bubble) bubble.style.display = 'none';
});
