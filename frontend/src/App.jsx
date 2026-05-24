import { useState, useEffect } from 'react'
import './App.css'

function App() {
  const [gameUrl, setGameUrl] = useState(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    const fetchGameUrl = async () => {
      try {
        const response = await fetch('/api/launch_game.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ 
            game_id: '8a704858d5deb4af1ddc722092ac7614',
            home_url: window.location.href,
            prefetch: 1
          })
        });
        const result = await response.json();
        if (result.success && result.game_url) {
          setGameUrl(result.game_url);
        }
      } catch (err) {
      } finally {
        setLoading(false);
      }
    };
    fetchGameUrl();
  }, []);

  return (
    <div className="altenar-desktop-app">
      {/* 1. TOP HEADER (100% Width) */}
      <header className="ad-top-header">
        <div className="ad-header-left">
          <div className="ad-logo">
            <span className="fire-icon">🔥</span> FIREWIN
          </div>
          <nav className="ad-main-nav">
            <a href="#" className="active">SPORTS</a>
            <a href="#">EN DIRECT</a>
            <a href="#">CASINO</a>
            <a href="#">CASINO EN DIRECT</a>
          </nav>
        </div>
        <div className="ad-header-right">
          <button className="ad-btn-login">Se connecter</button>
          <button className="ad-btn-register">S'inscrire</button>
        </div>
      </header>

      {/* 2. SUB NAVIGATION BAR (Dates & Sports Icons) */}
      <div className="ad-sub-header">
        <div className="ad-dates-row">
          <div className="ad-date active">
            <span className="day">Aujourd'hui</span>
            <span className="num">21</span>
          </div>
          <div className="ad-date">
            <span className="day">Ven</span>
            <span className="num">22</span>
          </div>
          <div className="ad-date">
            <span className="day">Sam</span>
            <span className="num">23</span>
          </div>
          <div className="ad-date">
            <span className="day">Dim</span>
            <span className="num">24</span>
          </div>
          <div className="ad-date">
            <span className="day">Lun</span>
            <span className="num">25</span>
          </div>
        </div>
        
        <div className="ad-sports-icons">
          <div className="ad-sport-icon active">
            <span className="icon">⚽</span> Football
          </div>
          <div className="ad-sport-icon">
            <span className="icon">🎾</span> Tennis
          </div>
          <div className="ad-sport-icon">
            <span className="icon">🏀</span> Basketball
          </div>
          <div className="ad-sport-icon">
            <span className="icon">🏒</span> Hockey
          </div>
        </div>
      </div>

      {/* 3. MAIN CONTENT AREA (Columns) */}
      <div className="ad-main-content">
        
        {/* Left Column: Leagues & Search */}
        <aside className="ad-left-sidebar">
          <div className="ad-search-box">
            <input type="text" placeholder="Rechercher équipe ou tournoi..." />
            <span className="search-icon">🔍</span>
          </div>

          <div className="ad-sidebar-section">
            <h3 className="section-title">⭐ FAVORIS</h3>
            <ul className="ad-league-list">
              <li>🇬🇧 Premier League</li>
              <li>🇪🇸 LaLiga</li>
              <li>🇮🇹 Serie A</li>
              <li>🇫🇷 Ligue 1</li>
              <li>🇩🇪 Bundesliga</li>
            </ul>
          </div>

          <div className="ad-sidebar-section">
            <h3 className="section-title">TOUS LES SPORTS</h3>
            <ul className="ad-sports-list">
              <li><span className="icon">⚽</span> Football <span className="count">984</span></li>
              <li><span className="icon">🎾</span> Tennis <span className="count">125</span></li>
              <li><span className="icon">🏀</span> Basketball <span className="count">89</span></li>
              <li><span className="icon">🏒</span> Hockey sur glace <span className="count">45</span></li>
              <li><span className="icon">🏐</span> Volleyball <span className="count">32</span></li>
            </ul>
          </div>
        </aside>

        {/* Center Column: The Sportsbook Iframe */}
        <main className="ad-center-column">
          {loading ? (
            <div className="ad-loading">Chargement du sportsbook...</div>
          ) : (
            <iframe 
              src={gameUrl} 
              className="ad-iframe"
              title="Sportsbook"
            ></iframe>
          )}
        </main>
        
        {/* Right Column: Fake Betslip Area (Optional for layout balance) */}
        <aside className="ad-right-sidebar">
          <div className="ad-betslip-header">
            FICHE DE PARI (0)
          </div>
          <div className="ad-betslip-content">
            <p>Votre fiche de pari est vide.</p>
            <p className="subtext">Veuillez cliquer sur les cotes au centre pour ajouter des paris.</p>
          </div>
        </aside>

      </div>
    </div>
  )
}

export default App
