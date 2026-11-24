document.addEventListener('DOMContentLoaded', () => {
  const brand = document.querySelector('.brand');

  if (!brand) {
    console.error('Elemento .brand não encontrado no HTML!');
    return;
  }

  brand.addEventListener('click', (e) => {
    brand.classList.remove('girar');
    
    // Truque para reiniciar a animação CSS imediatamente
    void brand.offsetWidth; 
    
    brand.classList.add('girar');
  });
});
