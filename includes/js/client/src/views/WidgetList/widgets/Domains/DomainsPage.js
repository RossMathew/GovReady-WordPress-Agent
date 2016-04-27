import React, { Component, PropTypes } from 'react';

class DomainsPage extends Component {

  render () {
    return (
      <div>
        {this.props.header}
        <h3>Domains</h3>
        <div className='domains'>
          {this.props.domains.map((domain) => (
            <div>
              <p><strong>Domain:</strong><span> {domain.domain}</span></p>
              <p><strong>Expires:</strong><span>  {domain.expires}</span></p>
              <p>
                <strong>Whois record:</strong>
                <br/>
                <span>{domain.whois}</span>
              </p>
            </div>
          ))}
          
        </div>
        <div>
          <h3>SSL</h3>
          {!this.props.ssl.domain && (
            <p className="alert alert-warning">No SSL active</p>
          )}
          {this.props.ssl.domain && (
            <div>
              <p><strong>Domain:</strong><span>  {this.props.ssl.domain}</span></p>
              <p><strong>Expires:</strong><span>  {this.props.ssl.expires}</span></p>
              <p><strong>Issued by:</strong><span>  {this.props.ssl.issuedBy}</span></p>
            </div>
          )}
        </div>
      </div>
    );
  }
}

DomainsPage.propTypes = {
  header: PropTypes.object.isRequired,
  domains: PropTypes.array.isRequired,
  ssl: PropTypes.object.isRequired
};

export default DomainsPage;